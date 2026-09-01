@extends('admin.cashbook.layouts.app')

@section('title', 'Cashbook Inventory Operational Controls — Green Leaf')

@section('header_title')
    <i data-lucide="boxes" class="w-5 h-5 text-emerald-600"></i> Cashbook Inventory
@endsection

@section('header_subtitle')
    Authoritative invoice adjustments, warehouse loadouts, bill pending matching, and movement audit.
@endsection

@section('content')
    <div class="mx-auto max-w-6xl space-y-6"
         x-data="{
             matchModalOpen: false,
             selectedGrnId: null,
             selectedGrnNumber: '',
             selectedGrnSupplier: '',
             detailModalOpen: false,
             selectedInvoice: null
         }">

        <!-- Page Header & Section Navigation -->
        <div class="flex flex-col gap-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Cashbook Inventory</h1>
                    <p class="text-xs font-bold text-slate-500 mt-0.5">Authoritative invoice adjustments &amp; inventory operational controls</p>
                </div>

                <!-- Navigation Tabs: 5 Sections -->
                <div class="flex flex-wrap gap-1 rounded-2xl bg-slate-100 p-1 shadow-inner self-start sm:self-auto">
                    <a href="{{ route('admin.cashbook.inventory', array_filter(['section' => 'invoice_adjustments', 'timeframe' => $timeframe, 'date' => $selectedDate, 'shop_id' => $shopId, 'search' => $search])) }}"
                       class="inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-black transition-all {{ $section === 'invoice_adjustments' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                        <i data-lucide="sliders-horizontal" class="w-3.5 h-3.5 {{ $section === 'invoice_adjustments' ? 'text-emerald-600' : 'text-slate-400' }}"></i>
                        Invoice Adjustments
                    </a>
                    <a href="{{ route('admin.cashbook.inventory', array_filter(['section' => 'bill_notes', 'timeframe' => $timeframe, 'date' => $selectedDate, 'shop_id' => $shopId, 'search' => $search])) }}"
                       class="inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-black transition-all {{ $section === 'bill_notes' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                        <i data-lucide="notebook-pen" class="w-3.5 h-3.5 {{ $section === 'bill_notes' ? 'text-emerald-600' : 'text-slate-400' }}"></i>
                        Bill Notes
                    </a>
                    <a href="{{ route('admin.cashbook.inventory', array_filter(['section' => 'bill_pending', 'timeframe' => $timeframe, 'date' => $selectedDate, 'search' => $search])) }}"
                       class="inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-black transition-all {{ $section === 'bill_pending' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                        <i data-lucide="clock" class="w-3.5 h-3.5 {{ $section === 'bill_pending' ? 'text-amber-600' : 'text-slate-400' }}"></i>
                        Bill Pending
                    </a>
                    <a href="{{ route('admin.cashbook.inventory', array_filter(['section' => 'loadout_not_billed', 'timeframe' => $timeframe, 'date' => $selectedDate, 'search' => $search])) }}"
                       class="inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-black transition-all {{ $section === 'loadout_not_billed' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                        <i data-lucide="truck" class="w-3.5 h-3.5 {{ $section === 'loadout_not_billed' ? 'text-blue-600' : 'text-slate-400' }}"></i>
                        Loadout Without Bill
                    </a>
                    <a href="{{ route('admin.cashbook.inventory', array_filter(['section' => 'history', 'timeframe' => $timeframe, 'date' => $selectedDate, 'search' => $search])) }}"
                       class="inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-black transition-all {{ $section === 'history' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                        <i data-lucide="history" class="w-3.5 h-3.5 {{ $section === 'history' ? 'text-purple-600' : 'text-slate-400' }}"></i>
                        History
                    </a>
                </div>
            </div>

            <!-- Day-First Filters & Search Bar -->
            <div class="rounded-3xl border border-slate-200/90 bg-white p-4 shadow-xs space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <!-- Quick Date Switchers -->
                    <div class="inline-flex items-center gap-1 rounded-2xl bg-slate-100 p-1">
                        <a href="{{ route('admin.cashbook.inventory', array_filter(['section' => $section, 'timeframe' => 'today', 'shop_id' => $shopId, 'reason' => $reasonFilter, 'resolution' => $resolutionFilter, 'search' => $search])) }}"
                           class="rounded-xl px-3 py-1.5 text-xs font-black transition-all {{ $timeframe === 'today' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                            Today
                        </a>
                        <a href="{{ route('admin.cashbook.inventory', array_filter(['section' => $section, 'timeframe' => 'yesterday', 'shop_id' => $shopId, 'reason' => $reasonFilter, 'resolution' => $resolutionFilter, 'search' => $search])) }}"
                           class="rounded-xl px-3 py-1.5 text-xs font-black transition-all {{ $timeframe === 'yesterday' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                            Yesterday
                        </a>
                    </div>

                    <!-- Date Picker Form -->
                    <form method="GET" action="{{ route('admin.cashbook.inventory') }}" class="flex flex-wrap items-center gap-2">
                        <input type="hidden" name="section" value="{{ $section }}">
                        <input type="hidden" name="timeframe" value="custom">
                        @if($shopId) <input type="hidden" name="shop_id" value="{{ $shopId }}"> @endif
                        @if($reasonFilter) <input type="hidden" name="reason" value="{{ $reasonFilter }}"> @endif
                        @if($resolutionFilter) <input type="hidden" name="resolution" value="{{ $resolutionFilter }}"> @endif
                        @if($search) <input type="hidden" name="search" value="{{ $search }}"> @endif

                        <x-cashbook.previous-month-button mode="as_of" size="sm" label="{{ now()->startOfMonth()->subDay()->format('M') }}" />
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

                <!-- Secondary Compact Filters -->
                <form method="GET" action="{{ route('admin.cashbook.inventory') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-2 pt-2 border-t border-slate-100">
                    <input type="hidden" name="section" value="{{ $section }}">
                    <input type="hidden" name="timeframe" value="{{ $timeframe }}">
                    <input type="hidden" name="date" value="{{ $selectedDate }}">

                    <!-- Search -->
                    <div class="relative">
                        <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                        <input type="text"
                               name="search"
                               value="{{ $search }}"
                               placeholder="Search receipt, invoice, shop, product..."
                               class="w-full pl-8 pr-3 py-1.5 text-xs font-medium rounded-xl bg-slate-50 border border-slate-200 shadow-2xs focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                    </div>

                    @if(in_array($section, ['invoice_adjustments', 'bill_notes'], true))
                        <!-- Shop Dropdown -->
                        <div>
                            <select name="shop_id" onchange="this.form.submit()" class="w-full px-2.5 py-1.5 text-xs font-semibold rounded-xl bg-slate-50 border border-slate-200 text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 cursor-pointer">
                                <option value="">All Shops</option>
                                @foreach($availableShops as $shop)
                                    <option value="{{ $shop->id }}" {{ (string) $shopId === (string) $shop->id ? 'selected' : '' }}>
                                        {{ $shop->name }} ({{ $shop->code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Reason Dropdown -->
                        <div>
                            <select name="reason" onchange="this.form.submit()" class="w-full px-2.5 py-1.5 text-xs font-semibold rounded-xl bg-slate-50 border border-slate-200 text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 cursor-pointer">
                                <option value="">All Reasons</option>
                                <option value="loadout_mistake" {{ $reasonFilter === 'loadout_mistake' ? 'selected' : '' }}>Loadout Mistake</option>
                                <option value="wastage_damage" {{ $reasonFilter === 'wastage_damage' ? 'selected' : '' }}>Wastage / Damage</option>
                                <option value="shop_delivery_mistake" {{ $reasonFilter === 'shop_delivery_mistake' ? 'selected' : '' }}>Shop / Delivery Mistake</option>
                                <option value="other" {{ $reasonFilter === 'other' ? 'selected' : '' }}>Other</option>
                            </select>
                        </div>

                        <!-- Resolution Dropdown & Submit -->
                        <div class="flex items-center gap-1.5">
                            <select name="resolution" onchange="this.form.submit()" class="w-full px-2.5 py-1.5 text-xs font-semibold rounded-xl bg-slate-50 border border-slate-200 text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 cursor-pointer">
                                <option value="">All Resolutions</option>
                                <option value="return_to_warehouse" {{ $resolutionFilter === 'return_to_warehouse' ? 'selected' : '' }}>Returned to Warehouse</option>
                                <option value="wastage" {{ $resolutionFilter === 'wastage' ? 'selected' : '' }}>Recorded as Wastage</option>
                                <option value="deduct_extra" {{ $resolutionFilter === 'deduct_extra' ? 'selected' : '' }}>Extra Stock Deducted</option>
                                <option value="already_accounted" {{ $resolutionFilter === 'already_accounted' ? 'selected' : '' }}>Already Accounted (No Stock Move)</option>
                            </select>

                            @if($search || $shopId || $reasonFilter || $resolutionFilter || $timeframe !== 'today')
                                <a href="{{ route('admin.cashbook.inventory', ['section' => $section]) }}"
                                   title="Reset filters"
                                   class="flex-none p-1.5 text-slate-400 hover:text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition">
                                    <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                                </a>
                            @endif
                        </div>
                    @else
                        <div></div><div></div>
                        <div class="flex justify-end">
                            <button type="submit" class="px-4 py-1.5 text-xs font-black rounded-xl bg-slate-900 text-white shadow-xs hover:bg-slate-800 transition-all">
                                Search
                            </button>
                        </div>
                    @endif
                </form>
            </div>
        </div>

        @if(in_array($section, ['invoice_adjustments', 'bill_notes'], true))
            <!-- Summary Cards for Selected Day -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <div class="rounded-3xl border border-slate-200/90 bg-white p-4 shadow-xs flex items-center justify-between">
                    <div>
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Total Adjustments</span>
                        <h3 class="text-2xl font-black text-slate-900 mt-0.5">{{ $summary['total_adjustments'] }}</h3>
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
                            {{ rtrim(rtrim(number_format($summary['returned_to_warehouse_qty'], 2), '0'), '.') }} <span class="text-xs font-bold text-emerald-700">Units</span>
                        </h3>
                        <p class="text-[11px] font-semibold text-emerald-700/90 mt-0.5">{{ $summary['returned_to_warehouse_count'] }} item(s) restored via SaleReversal</p>
                    </div>
                    <div class="w-10 h-10 rounded-2xl bg-emerald-100 text-emerald-800 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="rotate-ccw" class="w-5 h-5"></i>
                    </div>
                </div>

                <div class="rounded-3xl border border-rose-100 bg-rose-50/40 p-4 shadow-xs flex items-center justify-between">
                    <div>
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-rose-800">Recorded as Wastage</span>
                        <h3 class="text-2xl font-black text-rose-900 mt-0.5">
                            {{ rtrim(rtrim(number_format($summary['wastage_qty'], 2), '0'), '.') }} <span class="text-xs font-bold text-rose-700">Units</span>
                        </h3>
                        <p class="text-[11px] font-semibold text-rose-700/90 mt-0.5">{{ $summary['wastage_count'] }} item(s) booked to Wastage Entry</p>
                    </div>
                    <div class="w-10 h-10 rounded-2xl bg-rose-100 text-rose-800 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="trash-2" class="w-5 h-5"></i>
                    </div>
                </div>
            </div>
        @endif

        @if(in_array($section, ['invoice_adjustments', 'bill_notes'], true))
            <!-- INVOICE ADJUSTMENTS & BILL NOTES (ONE ROW PER INVOICE TABLE) -->
            <div class="space-y-4">
                <div class="flex items-center justify-between px-1">
                    <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider flex items-center gap-2">
                        <i data-lucide="{{ $section === 'bill_notes' ? 'notebook-pen' : 'sliders-horizontal' }}" class="w-4 h-4 text-emerald-600"></i>
                        {{ $section === 'bill_notes' ? 'Bill Notes & Changes' : 'Invoice Adjustments Audit' }}
                    </h2>
                    <span class="text-xs font-bold text-slate-500">
                        Showing {{ $adjustedInvoices ? $adjustedInvoices->total() : 0 }} bill(s) • 1 entry per bill with all details in popup
                    </span>
                </div>

                @if(!$adjustedInvoices || $adjustedInvoices->isEmpty())
                    <div class="p-12 text-center bg-white rounded-3xl border border-slate-200/90 shadow-xs">
                        <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center mx-auto mb-3">
                            <i data-lucide="check-circle-2" class="w-6 h-6"></i>
                        </div>
                        <h3 class="text-base font-black text-slate-900">No Changes Recorded</h3>
                        <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                            No bills on {{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }} have adjustment records matching your filter.
                        </p>
                    </div>
                @else
                    <div class="overflow-x-auto rounded-3xl border border-slate-200/90 bg-white shadow-xs">
                        <table class="w-full text-left text-xs min-w-[58rem]">
                            <thead class="bg-slate-50 text-[10px] font-extrabold uppercase tracking-wider text-slate-500 border-b border-slate-100">
                                <tr>
                                    <th class="p-3.5">Date &amp; Time</th>
                                    <th class="p-3.5">Shop</th>
                                    <th class="p-3.5">Invoice #</th>
                                    <th class="p-3.5">Changes &amp; Discrepancy Summary</th>
                                    <th class="p-3.5">Reason &amp; Resolution</th>
                                    <th class="p-3.5 text-right">Final Bill</th>
                                    <th class="p-3.5">Finalized By</th>
                                    <th class="p-3.5 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($adjustedInvoices as $inv)
                                    @php
                                        $invActivities = $activitiesByInvoice->get($inv->id, collect());
                                        $latestAct = $invActivities->first();
                                        $latestProps = $latestAct?->properties ?? [];

                                        // Collect all resolutions, product changes, discounts, notes from all activities for this invoice
                                        $allResolutions = [];
                                        $allItemChanges = [];
                                        $overallNotes = [];
                                        $isAdminOnBehalf = false;

                                        foreach ($invActivities as $actItem) {
                                            $actProps = $actItem->properties ?? [];
                                            $actRes = data_get($actProps, 'inventory_resolutions', []);
                                            if (is_array($actRes)) {
                                                foreach ($actRes as $r) { $allResolutions[] = $r; }
                                            }
                                            $source = data_get($actProps, 'source');
                                            if ($source === 'admin_item_adjustment') {
                                                $prodName = data_get($actProps, 'after.product_name') ?: data_get($actProps, 'before.product_name') ?: 'Item';
                                                $allItemChanges[] = [
                                                    'type' => 'item_adj',
                                                    'product_name' => $prodName,
                                                    'before_qty' => data_get($actProps, 'before.qty'),
                                                    'after_qty' => data_get($actProps, 'after.qty'),
                                                    'before_price' => data_get($actProps, 'before.price'),
                                                    'after_price' => data_get($actProps, 'after.price'),
                                                    'reason' => data_get($actProps, 'reason'),
                                                ];
                                            } elseif ($source === 'admin_discount') {
                                                $allItemChanges[] = [
                                                    'type' => 'discount',
                                                    'before' => data_get($actProps, 'before.discount_total', 0),
                                                    'after' => data_get($actProps, 'after.discount_total', 0),
                                                    'reason' => data_get($actProps, 'reason'),
                                                ];
                                            }
                                            if (data_get($actProps, 'is_admin_on_behalf')) {
                                                $isAdminOnBehalf = true;
                                            }
                                            $note = data_get($actProps, 'overall_note');
                                            if ($note && !in_array($note, $overallNotes, true)) {
                                                $overallNotes[] = $note;
                                            }
                                        }

                                        if ($inv->delivery_note && !in_array($inv->delivery_note, $overallNotes, true)) {
                                            $overallNotes[] = $inv->delivery_note;
                                        }

                                        $actorName = $inv->finalizedBy?->name ?? data_get($latestProps, 'actor.name') ?? $latestAct?->causer?->name ?? 'Admin';
                                        $autoSummary = data_get($latestProps, 'auto_change_summary');

                                        // Prepare JSON payload for Alpine popup
                                        $invoiceModalPayload = [
                                            'id' => $inv->id,
                                            'invoice_number' => $inv->invoice_number,
                                            'shop_name' => $inv->shop?->name ?? 'Shop',
                                            'shop_code' => $inv->shop?->code ?? '',
                                            'final_total' => (float) $inv->final_total,
                                            'subtotal' => (float) $inv->subtotal,
                                            'discount_total' => (float) $inv->discount_total,
                                            'status' => $inv->isFinalized() ? 'FINALIZED' : strtoupper(str_replace('_', ' ', $inv->status)),
                                            'finalized_by' => $actorName,
                                            'finalized_at' => $inv->finalized_at?->format('d M Y • H:i') ?? $inv->updated_at?->format('d M Y • H:i'),
                                            'is_admin_on_behalf' => $isAdminOnBehalf,
                                            'overall_notes' => $overallNotes,
                                            'auto_change_summary' => $autoSummary,
                                            'resolutions' => $allResolutions,
                                            'item_changes' => $allItemChanges,
                                            'activities_count' => $invActivities->count(),
                                        ];
                                    @endphp
                                    <tr class="hover:bg-slate-50/70 transition-colors">
                                        <!-- Date & Time (Bill Date + Updated Small Info) -->
                                        <td class="p-3.5 whitespace-nowrap align-top">
                                            <div class="font-bold text-slate-900">{{ $inv->business_date?->format('d M Y') ?? $inv->created_at?->format('d M Y') }}</div>
                                            <div class="text-[10px] font-bold text-slate-400 mt-0.5">
                                                Updated: <span class="font-mono text-slate-500">{{ ($inv->finalized_at ?? $inv->updated_at)?->format('d M • H:i') }}</span>
                                                <span class="text-slate-400">({{ ($inv->finalized_at ?? $inv->updated_at)?->diffForHumans() }})</span>
                                            </div>
                                        </td>

                                        <!-- Shop -->
                                        <td class="p-3.5 align-top">
                                            <div class="font-black text-slate-900">{{ $inv->shop?->name }}</div>
                                            @if($inv->shop?->code)
                                                <span class="font-mono text-[10px] font-bold text-slate-400">{{ $inv->shop?->code }}</span>
                                            @endif
                                        </td>

                                        <!-- Invoice # (Opens Popup Modal Only) -->
                                        <td class="p-3.5 whitespace-nowrap align-top">
                                            <button type="button"
                                                    @click="selectedInvoice = {{ json_encode($invoiceModalPayload) }}; detailModalOpen = true;"
                                                    class="font-mono text-xs font-black text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded-lg border border-emerald-200 hover:bg-emerald-100 transition inline-block text-left cursor-pointer">
                                                {{ $inv->invoice_number }}
                                            </button>
                                            <div class="mt-1 flex flex-wrap gap-1">
                                                <span class="inline-flex items-center rounded px-1.5 py-0.2 text-[9px] font-black uppercase {{ $inv->isFinalized() ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                                                    {{ $inv->isFinalized() ? 'FINALIZED' : 'PENDING' }}
                                                </span>
                                                @if($isAdminOnBehalf)
                                                    <span class="text-[9px] font-extrabold uppercase px-1.5 py-0.2 rounded bg-purple-50 text-purple-700 border border-purple-200">
                                                        On Behalf
                                                    </span>
                                                @endif
                                            </div>
                                        </td>

                                        <!-- Discrepancy & Changes Summary -->
                                        <td class="p-3.5 align-top space-y-1">
                                            @if(count($allResolutions) > 0)
                                                <div class="space-y-0.5">
                                                    @foreach($allResolutions as $r)
                                                        @php
                                                            $pName = $r['product_name'] ?? 'Product';
                                                            $lQty = isset($r['loaded_qty']) ? (float) $r['loaded_qty'] : null;
                                                            $fQty = isset($r['final_qty']) ? (float) $r['final_qty'] : null;
                                                            $dQty = ($lQty !== null && $fQty !== null) ? round($fQty - $lQty, 2) : 0;
                                                            $u = $r['unit'] ?? 'KG';
                                                        @endphp
                                                        <div class="text-xs">
                                                            <span class="font-black text-slate-900">{{ $pName }}:</span>
                                                            <span class="text-slate-500 font-mono">{{ $lQty !== null ? $lQty : '—' }}</span> →
                                                            <span class="font-bold text-slate-800 font-mono">{{ $fQty !== null ? $fQty : '—' }}</span>
                                                            <span class="font-mono font-black {{ $dQty < 0 ? 'text-rose-700' : ($dQty > 0 ? 'text-emerald-700' : 'text-slate-600') }}">
                                                                ({{ $dQty > 0 ? '+'.$dQty : $dQty }} {{ $u }})
                                                            </span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @elseif(count($allItemChanges) > 0)
                                                <div class="space-y-0.5">
                                                    @foreach($allItemChanges as $ch)
                                                        @if($ch['type'] === 'item_adj')
                                                            <div class="text-xs font-semibold text-slate-700">
                                                                {{ $ch['product_name'] }} adjusted
                                                                @if(isset($ch['before_qty'], $ch['after_qty']) && $ch['before_qty'] != $ch['after_qty'])
                                                                    <span class="font-mono font-bold">({{ $ch['before_qty'] }} → {{ $ch['after_qty'] }})</span>
                                                                @endif
                                                            </div>
                                                        @elseif($ch['type'] === 'discount')
                                                            <div class="text-xs font-semibold text-amber-800">
                                                                Discount: ₹{{ $ch['before'] }} → ₹{{ $ch['after'] }}
                                                                @if(!empty($ch['reason']))
                                                                    <span class="text-[10px] text-slate-500 italic">"{{ $ch['reason'] }}"</span>
                                                                @endif
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @elseif($autoSummary)
                                                <div class="text-xs font-mono text-slate-600 truncate max-w-xs" title="{{ $autoSummary }}">
                                                    {{ $autoSummary }}
                                                </div>
                                            @else
                                                <span class="text-xs text-slate-400">Bill finalized</span>
                                            @endif
                                        </td>

                                        <!-- Reason & Resolution Badges -->
                                        <td class="p-3.5 align-top space-y-1">
                                            @if(count($allResolutions) > 0)
                                                @foreach($allResolutions as $res)
                                                    @php
                                                        $rType = (string) ($res['resolution'] ?? '');
                                                        $bClass = match($rType) {
                                                            'return_to_warehouse', 'add_back' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                                            'wastage' => 'bg-rose-50 text-rose-800 border-rose-200',
                                                            'deduct_extra' => 'bg-blue-50 text-blue-800 border-blue-200',
                                                            default => 'bg-amber-50 text-amber-800 border-amber-200',
                                                        };
                                                        $rLabel = match($rType) {
                                                            'return_to_warehouse', 'add_back' => 'Returned to Warehouse',
                                                            'wastage' => 'Recorded as Wastage',
                                                            'deduct_extra' => 'Extra Stock Deducted from Warehouse',
                                                            'already_accounted' => 'Already Accounted',
                                                            default => ucfirst(str_replace('_', ' ', $rType)),
                                                        };
                                                    @endphp
                                                    <div>
                                                        <span class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[9px] font-black uppercase border {{ $bClass }}">
                                                            {{ $rLabel }}
                                                        </span>
                                                    </div>
                                                @endforeach
                                            @else
                                                <span class="text-xs text-slate-400">—</span>
                                            @endif
                                        </td>

                                        <!-- Final Bill Amount -->
                                        <td class="p-3.5 text-right whitespace-nowrap align-top">
                                            <div class="text-xs font-black text-slate-900 font-mono">₹{{ number_format((float) $inv->final_total, 2) }}</div>
                                        </td>

                                        <!-- Finalized By -->
                                        <td class="p-3.5 whitespace-nowrap align-top">
                                            <div class="text-xs font-bold text-slate-800">{{ $actorName }}</div>
                                            @if(!empty($overallNotes))
                                                <div class="text-[10px] text-slate-500 italic max-w-[12rem] truncate" title="{{ implode(' | ', $overallNotes) }}">
                                                    "{{ $overallNotes[0] }}"
                                                </div>
                                            @endif
                                        </td>

                                        <!-- Action: Popup & View Bill -->
                                        <td class="p-3.5 text-center whitespace-nowrap align-top space-x-1">
                                            <button type="button"
                                                    @click="selectedInvoice = {{ json_encode($invoiceModalPayload) }}; detailModalOpen = true;"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-slate-900 text-white text-[11px] font-black hover:bg-slate-800 transition shadow-2xs">
                                                <i data-lucide="eye" class="w-3 h-3"></i>
                                                <span>View</span>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $adjustedInvoices->links() }}
                    </div>
                @endif
            </div>

            <!-- ALL-DETAILS POPUP MODAL (ONE INVOICE PER DAY) -->
            <div x-show="detailModalOpen"
                 x-cloak
                 class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
                <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-2xl w-full p-5 sm:p-6 space-y-4 max-h-[90vh] overflow-y-auto"
                     @click.away="detailModalOpen = false">

                    <!-- Modal Header -->
                    <div class="flex items-start justify-between pb-3 border-b border-slate-100">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-black text-slate-900" x-text="selectedInvoice ? selectedInvoice.shop_name : ''"></h3>
                                <span class="font-mono text-xs font-black text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200"
                                      x-text="selectedInvoice ? selectedInvoice.invoice_number : ''"></span>
                                <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800"
                                      x-text="selectedInvoice ? selectedInvoice.status : ''"></span>
                            </div>
                            <div class="text-xs text-slate-500 font-bold mt-1 flex items-center gap-2">
                                <span x-text="selectedInvoice ? 'Final Amount: ₹' + Number(selectedInvoice.final_total).toFixed(2) : ''"></span>
                                <span>•</span>
                                <span x-text="selectedInvoice ? 'Finalized by ' + selectedInvoice.finalized_by + ' on ' + selectedInvoice.finalized_at : ''"></span>
                            </div>
                        </div>
                        <button type="button" @click="detailModalOpen = false" class="text-slate-400 hover:text-slate-600 p-1">
                            <i data-lucide="x" class="w-5 h-5"></i>
                        </button>
                    </div>

                    <!-- On-Behalf Notice -->
                    <template x-if="selectedInvoice && selectedInvoice.is_admin_on_behalf">
                        <div class="bg-purple-50 p-2.5 rounded-xl border border-purple-200 text-xs font-black text-purple-900 flex items-center gap-2">
                            <i data-lucide="shield-alert" class="w-4 h-4 text-purple-700"></i>
                            <span>FINALIZED BY ADMIN ON BEHALF OF SHOP</span>
                        </div>
                    </template>

                    <!-- Discrepancies & Inventory Resolutions Table -->
                    <template x-if="selectedInvoice && selectedInvoice.resolutions && selectedInvoice.resolutions.length > 0">
                        <div class="space-y-2">
                            <h4 class="text-xs font-black uppercase tracking-wider text-slate-600">Product Discrepancies &amp; Inventory Outcomes</h4>
                            <div class="space-y-2">
                                <template x-for="res in selectedInvoice.resolutions" :key="res.product_name">
                                    <div class="bg-slate-50 p-3 rounded-2xl border border-slate-200 space-y-2">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-black text-slate-900" x-text="res.product_name"></span>
                                            <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase border"
                                                  :class="res.resolution === 'return_to_warehouse' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : (res.resolution === 'wastage' ? 'bg-rose-50 text-rose-800 border-rose-200' : (res.resolution === 'deduct_extra' ? 'bg-blue-50 text-blue-800 border-blue-200' : 'bg-amber-50 text-amber-800 border-amber-200'))"
                                                  x-text="res.resolution === 'return_to_warehouse' ? 'RETURNED TO WAREHOUSE ' + res.resolution_qty + ' ' + (res.unit || 'KG') : (res.resolution === 'wastage' ? 'RECORDED AS WASTAGE ' + res.resolution_qty + ' ' + (res.unit || 'KG') : (res.resolution === 'deduct_extra' ? 'EXTRA STOCK DEDUCTED ' + res.resolution_qty + ' ' + (res.unit || 'KG') : 'ALREADY ACCOUNTED ' + res.resolution_qty + ' ' + (res.unit || 'KG')))">
                                            </span>
                                        </div>

                                        <div class="grid grid-cols-3 gap-2 bg-white p-2 rounded-xl border border-slate-200 text-center text-xs">
                                            <div>
                                                <span class="text-[9px] font-extrabold uppercase text-slate-400 block">Loaded</span>
                                                <span class="font-mono font-bold text-slate-700" x-text="(res.loaded_qty ?? '—') + ' ' + (res.unit || 'KG')"></span>
                                            </div>
                                            <div>
                                                <span class="text-[9px] font-extrabold uppercase text-slate-400 block">Final Bill</span>
                                                <span class="font-mono font-black text-slate-900" x-text="(res.final_qty ?? '—') + ' ' + (res.unit || 'KG')"></span>
                                            </div>
                                            <div>
                                                <span class="text-[9px] font-extrabold uppercase text-slate-400 block">Difference</span>
                                                <span class="font-mono font-black"
                                                      :class="(res.final_qty - res.loaded_qty) < 0 ? 'text-rose-700' : 'text-emerald-700'"
                                                      x-text="((res.final_qty - res.loaded_qty) > 0 ? '+' : '') + (res.final_qty - res.loaded_qty) + ' ' + (res.unit || 'KG')"></span>
                                            </div>
                                        </div>

                                        <div class="text-xs text-slate-600 flex flex-wrap justify-between gap-1 pt-0.5">
                                            <div>
                                                <span class="font-bold text-slate-700">Reason:</span>
                                                <span x-text="res.reason ? res.reason.replace(/_/g, ' ') : 'Loadout Mistake'"></span>
                                            </div>
                                            <template x-if="res.item_note">
                                                <div class="italic text-slate-500" x-text="'&quot;' + res.item_note + '&quot;'"></div>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <!-- Item Adjustments & Price Modifications -->
                    <template x-if="selectedInvoice && selectedInvoice.item_changes && selectedInvoice.item_changes.length > 0">
                        <div class="space-y-2">
                            <h4 class="text-xs font-black uppercase tracking-wider text-slate-600">Item Modifications &amp; Discounts</h4>
                            <div class="space-y-1.5">
                                <template x-for="(ch, idx) in selectedInvoice.item_changes" :key="idx">
                                    <div class="p-2.5 rounded-xl border border-slate-200 bg-slate-50 text-xs flex items-center justify-between">
                                        <div>
                                            <template x-if="ch.type === 'item_adj'">
                                                <div>
                                                    <span class="font-bold text-slate-900" x-text="ch.product_name"></span>
                                                    <span class="text-slate-600 ml-2" x-text="'Qty: ' + (ch.before_qty ?? '—') + ' → ' + (ch.after_qty ?? '—')"></span>
                                                </div>
                                            </template>
                                            <template x-if="ch.type === 'discount'">
                                                <div class="font-bold text-amber-900" x-text="'Discount Applied: ₹' + ch.before + ' → ₹' + ch.after"></div>
                                            </template>
                                        </div>
                                        <template x-if="ch.reason">
                                            <div class="text-slate-500 italic text-[11px]" x-text="'&quot;' + ch.reason + '&quot;'"></div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <!-- Auto Change Summary -->
                    <template x-if="selectedInvoice && selectedInvoice.auto_change_summary">
                        <div class="space-y-1">
                            <h4 class="text-xs font-black uppercase tracking-wider text-slate-600">Audit Summary</h4>
                            <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 text-[11px] font-mono text-slate-700 whitespace-pre-line leading-relaxed"
                                 x-text="selectedInvoice.auto_change_summary"></div>
                        </div>
                    </template>

                    <!-- Overall Notes -->
                    <template x-if="selectedInvoice && selectedInvoice.overall_notes && selectedInvoice.overall_notes.length > 0">
                        <div class="space-y-1">
                            <h4 class="text-xs font-black uppercase tracking-wider text-slate-600">Overall Notes</h4>
                            <template x-for="(nt, nidx) in selectedInvoice.overall_notes" :key="nidx">
                                <div class="text-xs text-slate-700 bg-teal-50/50 p-2.5 rounded-xl border border-teal-100 flex items-start gap-2">
                                    <i data-lucide="message-square" class="w-4 h-4 text-teal-600 flex-shrink-0 mt-0.5"></i>
                                    <span x-text="'&quot;' + nt + '&quot;'"></span>
                                </div>
                            </template>
                        </div>
                    </template>

                    <!-- Modal Actions -->
                    <div class="flex items-center justify-end pt-3 border-t border-slate-100">
                        <button type="button" @click="detailModalOpen = false" class="px-5 py-2 text-xs font-black rounded-xl bg-slate-900 text-white hover:bg-slate-800 transition shadow-xs cursor-pointer">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        @endif

        @if($section === 'bill_pending')
            <!-- SECTION 3: BILL PENDING -->
            <div class="space-y-4">
                <div class="flex items-center justify-between px-1">
                    <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider flex items-center gap-2">
                        <i data-lucide="clock" class="w-4 h-4 text-amber-600"></i>
                        Goods Received — Bill Pending
                    </h2>
                    <span class="text-xs font-bold text-slate-500">
                        Showing {{ $billPendingReceipts->total() }} pending receipt(s)
                    </span>
                </div>

                @if($billPendingReceipts->isEmpty())
                    <div class="p-12 text-center bg-white rounded-3xl border border-slate-200/90 shadow-xs">
                        <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-3">
                            <i data-lucide="check-circle-2" class="w-6 h-6"></i>
                        </div>
                        <h3 class="text-base font-black text-slate-900">All Physical Receipts Billed</h3>
                        <p class="text-xs text-slate-500 mt-1">There are no pending warehouse goods receipts without a vendor bill.</p>
                    </div>
                @else
                    <div class="grid grid-cols-1 gap-3.5">
                        @foreach($billPendingReceipts as $grn)
                            <div class="bg-white rounded-3xl border border-slate-200/90 p-4 sm:p-5 shadow-xs hover:shadow-md transition-all space-y-3">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5 pb-3 border-b border-slate-100">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-2xl bg-amber-50 text-amber-700 flex items-center justify-center font-black text-xs flex-shrink-0">
                                            GRN
                                        </div>
                                        <div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h3 class="text-sm font-black text-slate-900">{{ $grn->purchaseOrder?->supplier?->name ?? 'Vendor Unassigned' }}</h3>
                                                <span class="font-mono text-xs font-black text-amber-800 bg-amber-50 px-2 py-0.5 rounded-lg border border-amber-200">
                                                    {{ $grn->grn_number }}
                                                </span>
                                                <span class="inline-flex items-center rounded-lg bg-amber-100 px-2 py-0.5 text-[10px] font-black text-amber-800">
                                                    BILL PENDING
                                                </span>
                                            </div>
                                            <div class="text-[11px] font-bold text-slate-400 mt-0.5 flex items-center gap-1.5 flex-wrap">
                                                <span>{{ $grn->warehouse?->name ?? $grn->destinationShop?->name ?? 'Central Warehouse' }}</span>
                                                <span>•</span>
                                                <span>Received by {{ $grn->receivedBy?->name ?? 'Receiver' }}</span>
                                                <span>•</span>
                                                <span>{{ $grn->received_at?->format('d M Y') }} ({{ $grn->received_at?->diffForHumans() }})</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <button type="button"
                                                @click="matchModalOpen = true; selectedGrnId = {{ $grn->id }}; selectedGrnNumber = '{{ $grn->grn_number }}'; selectedGrnSupplier = '{{ addslashes($grn->purchaseOrder?->supplier?->name ?? 'Vendor') }}'"
                                                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-amber-600 text-white text-xs font-black hover:bg-amber-500 transition shadow-xs">
                                            <i data-lucide="link" class="w-3.5 h-3.5"></i>
                                            <span>Match Bill</span>
                                        </button>
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    @foreach($grn->items as $item)
                                        <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-slate-50 border border-slate-200/80 text-xs font-bold text-slate-800">
                                            <span>{{ $item->product?->name ?? 'Item #'.$item->product_id }}</span>
                                            <span class="text-emerald-700 font-black">{{ (float) $item->received_qty }} {{ $item->product?->unit ?? 'KG' }}</span>
                                        </div>
                                    @endforeach
                                </div>

                                @if($grn->notes)
                                    <div class="text-xs text-slate-600 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                                        <span class="font-bold text-slate-700">Notes:</span> {{ $grn->notes }}
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4">
                        {{ $billPendingReceipts->links() }}
                    </div>
                @endif
            </div>

            <!-- Match Bill Modal -->
            <div x-show="matchModalOpen"
                 x-cloak
                 class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
                <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-md w-full p-6 space-y-4"
                     @click.away="matchModalOpen = false">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <div>
                            <h3 class="text-base font-black text-slate-900">Match Purchase Bill</h3>
                            <p class="text-xs text-slate-500 font-semibold" x-text="'GRN: ' + selectedGrnNumber + ' • ' + selectedGrnSupplier"></p>
                        </div>
                        <button type="button" @click="matchModalOpen = false" class="text-slate-400 hover:text-slate-600">
                            <i data-lucide="x" class="w-5 h-5"></i>
                        </button>
                    </div>

                    <form :action="'/admin/cashbook/inventory/match-bill/' + selectedGrnId" method="POST" class="space-y-3.5">
                        @csrf
                        <div>
                            <label class="block text-xs font-black text-slate-700 uppercase mb-1">Purchaser Invoice / Bill #</label>
                            <input type="text"
                                   name="invoice_number"
                                   required
                                   placeholder="e.g. BILL-20260826-001"
                                   class="w-full px-3 py-2 text-xs font-semibold rounded-xl bg-slate-50 border border-slate-200 focus:bg-white focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                        </div>

                        <div>
                            <label class="block text-xs font-black text-slate-700 uppercase mb-1">Total Bill Amount (₹)</label>
                            <input type="number"
                                   step="0.01"
                                   name="amount"
                                   required
                                   placeholder="0.00"
                                   class="w-full px-3 py-2 text-xs font-semibold rounded-xl bg-slate-50 border border-slate-200 focus:bg-white focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500">
                        </div>

                        <div>
                            <label class="block text-xs font-black text-slate-700 uppercase mb-1">Reconciliation Notes</label>
                            <textarea name="notes"
                                      rows="2"
                                      placeholder="Optional note regarding vendor invoice matching..."
                                      class="w-full px-3 py-2 text-xs font-medium rounded-xl bg-slate-50 border border-slate-200 focus:bg-white focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500"></textarea>
                        </div>

                        <div class="bg-amber-50 p-3 rounded-xl border border-amber-200 text-[11px] font-bold text-amber-900">
                            <i data-lucide="info" class="w-3.5 h-3.5 inline mr-1 text-amber-700"></i>
                            Stock has already been entered into inventory. Matching will link the financial bill without creating duplicate stock movements.
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                            <button type="button" @click="matchModalOpen = false" class="px-3.5 py-2 text-xs font-bold rounded-xl text-slate-600 hover:bg-slate-100">
                                Cancel
                            </button>
                            <button type="submit" class="px-4 py-2 text-xs font-black rounded-xl bg-amber-600 text-white hover:bg-amber-500 shadow-xs">
                                Confirm Match
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        @elseif($section === 'loadout_not_billed')
            <!-- SECTION 4: LOADOUT WITHOUT BILL -->
            <div class="space-y-4">
                <div class="flex items-center justify-between px-1">
                    <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider flex items-center gap-2">
                        <i data-lucide="truck" class="w-4 h-4 text-blue-600"></i>
                        Loadout Without Bill
                    </h2>
                    <span class="text-xs font-bold text-slate-500">
                        Showing {{ $loadoutNotBilled->total() }} unbilled loadout movement(s)
                    </span>
                </div>

                @if($loadoutNotBilled->isEmpty())
                    <div class="p-12 text-center bg-white rounded-3xl border border-slate-200/90 shadow-xs">
                        <div class="w-12 h-12 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center mx-auto mb-3">
                            <i data-lucide="check-circle-2" class="w-6 h-6"></i>
                        </div>
                        <h3 class="text-base font-black text-slate-900">All Loadouts Covered</h3>
                        <p class="text-xs text-slate-500 mt-1">There are no dispatched loadouts missing purchaser bill coverage.</p>
                    </div>
                @else
                    <div class="grid grid-cols-1 gap-3.5">
                        @foreach($loadoutNotBilled as $loadout)
                            <div class="bg-white rounded-3xl border border-slate-200/90 p-4 sm:p-5 shadow-xs hover:shadow-md transition-all space-y-3">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5 pb-3 border-b border-slate-100">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-2xl bg-blue-50 text-blue-700 flex items-center justify-center font-black text-xs flex-shrink-0">
                                            <i data-lucide="package" class="w-4 h-4"></i>
                                        </div>
                                        <div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h3 class="text-sm font-black text-slate-900">{{ $loadout->product?->name ?? 'Product' }}</h3>
                                                <span class="inline-flex items-center rounded-lg bg-rose-100 px-2 py-0.5 text-[10px] font-black text-rose-800">
                                                    NOT BILLED
                                                </span>
                                            </div>
                                            <div class="text-[11px] font-bold text-slate-400 mt-0.5 flex items-center gap-1.5 flex-wrap">
                                                <span>{{ $loadout->shopOrderItem?->order?->shop?->name ?? 'Shop Order' }}</span>
                                                <span>•</span>
                                                <span>Warehouse: {{ $loadout->warehouse?->name ?? 'Main Warehouse' }}</span>
                                                <span>•</span>
                                                <span>{{ $loadout->created_at?->format('d M Y') }} ({{ $loadout->created_at?->diffForHumans() }})</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-right">
                                        <span class="text-[10px] font-extrabold uppercase text-slate-400 block">Dispatched Qty</span>
                                        <span class="text-sm font-black text-slate-900 font-mono">{{ (float) $loadout->quantity }} {{ $loadout->product?->unit ?? 'KG' }}</span>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs bg-slate-50 p-3 rounded-2xl border border-slate-100">
                                    <div>
                                        <span class="font-bold text-slate-700">Source Receipt:</span>
                                        <span class="text-slate-600 font-mono">{{ $loadout->batch?->goodsReceived?->grn_number ?? 'Direct Batch / None' }}</span>
                                    </div>
                                    <div>
                                        <span class="font-bold text-slate-700">Supplier:</span>
                                        <span class="text-slate-600">{{ $loadout->batch?->goodsReceived?->purchaseOrder?->supplier?->name ?? 'Unassigned' }}</span>
                                    </div>
                                </div>

                                @if($loadout->notes)
                                    <div class="text-xs text-slate-600">
                                        <span class="font-bold text-slate-700">Note:</span> {{ $loadout->notes }}
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4">
                        {{ $loadoutNotBilled->links() }}
                    </div>
                @endif
            </div>

        @elseif($section === 'history')
            <!-- SECTION 5: HISTORY -->
            <div class="space-y-4">
                <div class="flex items-center justify-between px-1">
                    <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider flex items-center gap-2">
                        <i data-lucide="history" class="w-4 h-4 text-purple-600"></i>
                        Permanent Resolution History
                    </h2>
                    <span class="text-xs font-bold text-slate-500">
                        Showing {{ $historyRecords->total() }} resolved record(s)
                    </span>
                </div>

                @if($historyRecords->isEmpty())
                    <div class="p-12 text-center bg-white rounded-3xl border border-slate-200/90 shadow-xs">
                        <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center mx-auto mb-3">
                            <i data-lucide="check-circle-2" class="w-6 h-6"></i>
                        </div>
                        <h3 class="text-base font-black text-slate-900">No History Records Found</h3>
                        <p class="text-xs text-slate-500 mt-1">No past resolution history matches your query.</p>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($historyRecords as $hist)
                            @php
                                $hProps = $hist->properties ?? [];
                                $isGRN = $hist->subject_type === \App\Models\GoodsReceived::class || $hist->event === 'goods_received.bill_matched';
                            @endphp
                            <div class="bg-white rounded-3xl border border-slate-200/90 p-4 sm:p-5 shadow-xs hover:shadow-md transition-all space-y-2.5">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pb-2.5 border-b border-slate-100">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-xl {{ $isGRN ? 'bg-amber-50 text-amber-700' : 'bg-purple-50 text-purple-700' }} flex items-center justify-center font-black text-xs">
                                            <i data-lucide="{{ $isGRN ? 'file-check' : 'shield-check' }}" class="w-4 h-4"></i>
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs font-black text-slate-900">
                                                    {{ $isGRN ? 'Bill Pending Matched' : 'Invoice Discrepancy Resolved' }}
                                                </span>
                                                <span class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[9px] font-black uppercase bg-emerald-50 text-emerald-800 border border-emerald-200">
                                                    RESOLVED
                                                </span>
                                            </div>
                                            <div class="text-[11px] font-bold text-slate-400">
                                                By {{ $hist->causer?->name ?? 'Admin' }} • {{ $hist->created_at?->format('d M Y • H:i') }}
                                            </div>
                                        </div>
                                    </div>

                                    @if(!$isGRN && $hist->subject)
                                        <a href="{{ route('purchasing.shop-invoices.show', $hist->subject) }}"
                                           class="inline-flex items-center gap-1 text-xs font-black text-emerald-700 hover:text-emerald-900">
                                            <span>View Invoice {{ $hist->subject->invoice_number }}</span>
                                            <i data-lucide="arrow-up-right" class="w-3 h-3"></i>
                                        </a>
                                    @endif
                                </div>

                                <div class="text-xs text-slate-600 font-mono bg-slate-50 p-2.5 rounded-xl border border-slate-100 whitespace-pre-line">
@if($isGRN)
Bill Number: {{ data_get($hProps, 'invoice_number') }} | Amount: ₹{{ number_format((float) data_get($hProps, 'amount', 0), 2) }}
@else
{{ data_get($hProps, 'auto_change_summary') ?: 'Invoice audit adjustments finalized.' }}
@endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4">
                        {{ $historyRecords->links() }}
                    </div>
                @endif
            </div>
        @endif

    </div>
@endsection
