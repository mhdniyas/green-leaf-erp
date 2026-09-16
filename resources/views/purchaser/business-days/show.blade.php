@extends('purchaser.business-days.layouts.app')

@section('title', 'Business Day: ' . $day->business_date->format('d M Y') . ' — ' . $warehouse->name)
@section('page_title', 'Business Day Details')
@section('page_description', 'Reconciliation, matching, and closing controls for ' . $warehouse->name . ' on ' . $day->business_date->format('d M Y') . '.')
@section('hide_mobile_bottom_nav', '1')

@section('content')
<!-- ========================================================================= -->
<!-- 1. MOBILE VIEW (Simplified Operational Flow: Summary → Pending → Drilldowns → Close) -->
<!-- ========================================================================= -->
<div class="block lg:hidden space-y-3 pb-20">
    <!-- Compact Mobile Header -->
    <div class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 shadow-xs">
        <div class="flex items-center justify-between gap-2">
            <div class="min-w-0">
                <div class="flex items-center gap-1.5">
                    <h2 class="text-xs font-black text-slate-900 truncate">
                        {{ $day->business_date->format('d M Y') }} · {{ $warehouse->name }}
                    </h2>
                </div>
                <p class="mt-0.5 text-[10px] font-semibold text-slate-500 leading-snug">
                    @if ($day->isOpen())
                        Opened by {{ $day->openedBy?->name ?? 'System' }} · {{ $day->opened_at?->format('h:i A') }}
                    @elseif ($day->isReopened())
                        Reopened · {{ $day->reopened_at?->format('h:i A') }}
                    @else
                        Closed · {{ $day->closed_at?->format('h:i A') }}
                    @endif
                </p>
            </div>
            <div class="flex items-center gap-1 shrink-0">
                @if ($day->isOpen())
                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-emerald-800">OPEN</span>
                @elseif ($day->isReopened())
                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-amber-800">REOPEN</span>
                @else
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-slate-700">CLOSED</span>
                @endif
                <a href="{{ route('purchasing.business-days.index', ['month' => $day->business_date->format('Y-m'), 'warehouse_id' => $day->warehouse_id, 'view' => 'history']) }}" class="inline-flex h-6 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 px-2 text-[9px] font-bold text-slate-600 hover:bg-slate-100">
                    ↩
                </a>
            </div>
        </div>
    </div>

    <!-- 4 Compact Summary Cards in 2x2 Grid -->
    <div class="grid grid-cols-2 gap-2">
        <!-- 1. Bills -->
        <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-xs">
            <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Bills</p>
            <p class="mt-0.5 text-xl font-black text-slate-900">{{ $managerSummary['purchase_bills']['count'] ?? $bills->count() }}</p>
            <p class="text-[10px] font-bold text-slate-500 truncate">{{ $managerSummary['purchase_bills']['formatted_totals'] ?? '0 kg' }}</p>
        </div>

        <!-- 2. Advance -->
        <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-xs">
            <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Advance</p>
            <p class="mt-0.5 text-xl font-black text-slate-900">{{ $managerSummary['advance_receives']['count'] ?? $advanceReceives->count() }}</p>
            <p class="text-[10px] font-bold text-slate-500 truncate">{{ $managerSummary['advance_receives']['formatted_totals'] ?? '0 kg' }}</p>
        </div>

        <!-- 3. Pending -->
        <div class="rounded-xl border {{ $pendingList->isNotEmpty() ? 'border-rose-200 bg-rose-50/50' : 'border-slate-200 bg-white' }} p-3 shadow-xs">
            <p class="text-[9px] font-black uppercase tracking-wider {{ $pendingList->isNotEmpty() ? 'text-rose-600' : 'text-slate-400' }}">Pending</p>
            <p class="mt-0.5 text-xl font-black {{ $pendingList->isNotEmpty() ? 'text-rose-600' : 'text-slate-900' }}">{{ $pendingList->count() }}</p>
            <p class="text-[10px] font-bold {{ $pendingList->isNotEmpty() ? 'text-rose-600' : 'text-slate-500' }} truncate">{{ $summary['total_pending_bill_qty'] ?? 0 }} qty</p>
        </div>

        <!-- 4. Issues -->
        <div class="rounded-xl border {{ ($summary['unit_fix_count'] ?? 0) > 0 ? 'border-amber-200 bg-amber-50/50' : 'border-slate-200 bg-white' }} p-3 shadow-xs">
            <p class="text-[9px] font-black uppercase tracking-wider {{ ($summary['unit_fix_count'] ?? 0) > 0 ? 'text-amber-700' : 'text-slate-400' }}">Issues</p>
            <p class="mt-0.5 text-xl font-black {{ ($summary['unit_fix_count'] ?? 0) > 0 ? 'text-amber-700' : 'text-slate-900' }}">{{ $summary['unit_fix_count'] ?? 0 }}</p>
            <p class="text-[10px] font-bold {{ ($summary['unit_fix_count'] ?? 0) > 0 ? 'text-amber-700' : 'text-slate-500' }}">
                {{ ($summary['unit_fix_count'] ?? 0) > 0 ? 'Unit fix' : 'OK' }}
            </p>
        </div>
    </div>

    <!-- Needs Attention (Pending First) -->
    <div class="rounded-xl border border-slate-200 bg-white px-3 py-3 shadow-xs space-y-2">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-1.5">
                <h3 class="text-[10px] font-black uppercase tracking-wider text-slate-700">Needs Attention</h3>
                @if ($pendingList->isNotEmpty())
                    <span class="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-100 px-1 text-[9px] font-black text-rose-700">
                        {{ $pendingList->count() }}
                    </span>
                @endif
            </div>
            @if ($pendingList->isNotEmpty() && ($day->isOpen() || $day->isReopened()))
                <a href="{{ route('purchasing.business-days.bills.create', $day->uuid) }}" class="text-[10px] font-black text-teal-700 hover:text-teal-800">
                    + Add Bill
                </a>
            @endif
        </div>

        @if ($pendingList->isNotEmpty())
            <div class="divide-y divide-slate-100">
                @foreach ($pendingList->take(3) as $item)
                    <div class="flex items-center justify-between py-2 first:pt-0 last:pb-0 gap-2">
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-black text-slate-900 truncate">{{ $item['product_name'] }}</p>
                            <p class="text-[10px] font-bold text-rose-600">{{ $item['formatted_pending'] }}</p>
                            @if ($item['unit_mismatch'])
                                <span class="inline-block rounded-full bg-rose-100 px-1.5 py-0.5 text-[8px] font-bold text-rose-700">Unit Mismatch</span>
                            @endif
                        </div>
                        <div class="shrink-0">
                            @if ($day->isOpen() || $day->isReopened())
                                <a href="{{ route('purchasing.business-days.bills.create', ['uuid' => $day->uuid, 'product_id' => $item['product_id']]) }}" class="inline-flex h-7 items-center justify-center rounded-lg bg-teal-600 px-2.5 text-[10px] font-black text-white hover:bg-teal-500">
                                    Add
                                </a>
                            @else
                                <span class="text-[9px] font-bold text-slate-400">Closed</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($pendingList->count() > 3)
                <div class="pt-1.5 border-t border-slate-100">
                    <button type="button" onclick="openMobileDrilldown('pending')" class="w-full text-center py-1.5 text-[10px] font-black text-teal-700 hover:text-teal-800 rounded-lg bg-teal-50">
                        View All ({{ $pendingList->count() }}) →
                    </button>
                </div>
            @endif
        @else
            <div class="rounded-lg bg-emerald-50 border border-emerald-100 px-3 py-2 text-center">
                <p class="text-[10px] font-black text-emerald-800">🎉 All Matched & Billed</p>
            </div>
        @endif
    </div>

    @if ($openCarryForwards->isNotEmpty())
        <!-- Previous Day Pending Tasks -->
        <div class="rounded-xl border border-teal-200 bg-teal-50/70 p-3 shadow-xs space-y-2">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-1.5">
                    <span class="flex h-4 w-4 items-center justify-center rounded-md bg-teal-600 text-white text-[10px] font-black">↳</span>
                    <h3 class="text-[10px] font-black uppercase tracking-wider text-teal-950">Previous Day Pending</h3>
                </div>
                <span class="inline-flex items-center rounded-full bg-teal-100 px-2 py-0.5 text-[9px] font-black text-teal-800">
                    {{ $openCarryForwards->count() }} Tasks
                </span>
            </div>

            <div class="divide-y divide-teal-100/80">
                @foreach ($openCarryForwards as $cItem)
                    <div class="flex items-center justify-between py-2 first:pt-0 last:pb-0 gap-2">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1">
                                <span class="text-[9px] font-black text-teal-700">{{ $cItem['origin_date_formatted'] }}</span>
                                <span class="text-slate-300">•</span>
                                <span class="text-[11px] font-black text-slate-900 truncate">{{ $cItem['product_name'] }}</span>
                            </div>
                            <p class="text-[10px] font-bold text-rose-600">{{ $cItem['formatted_pending'] }} pending</p>
                        </div>
                        <div class="shrink-0">
                            <a href="{{ route('purchasing.business-days.bills.create', ['uuid' => $cItem['origin_day_uuid'], 'carry_forward' => $cItem['uuid'], 'product_id' => $cItem['product_id']]) }}"
                               class="inline-flex h-7 items-center justify-center rounded-lg bg-teal-600 px-2.5 text-[10px] font-black text-white hover:bg-teal-500 transition">
                                + Add Bill
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- More For This Day (Drill-Down Links) -->
    <div class="rounded-xl border border-slate-200 bg-white shadow-xs overflow-hidden">
        <div class="border-b border-slate-100 px-3 py-2">
            <h3 class="text-[9px] font-black uppercase tracking-wider text-slate-400">Details</h3>
        </div>
        <div class="divide-y divide-slate-100">
            <!-- 1. Inventory Details -->
            <button type="button" onclick="openMobileDrilldown('inventory')" class="flex w-full items-center justify-between px-3 py-2.5 text-left text-[11px] font-black text-slate-800 hover:bg-slate-50 transition">
                <span class="flex items-center gap-2">
                    <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" /></svg>
                    <span>Inventory</span>
                </span>
                <span class="flex items-center gap-1 text-slate-400">
                    <span class="text-[10px] font-bold text-slate-500">{{ $comparisonRows->count() }}</span>
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                </span>
            </button>

            <!-- 2. Purchase Bills -->
            <button type="button" onclick="openMobileDrilldown('bills')" class="flex w-full items-center justify-between px-3 py-2.5 text-left text-[11px] font-black text-slate-800 hover:bg-slate-50 transition">
                <span class="flex items-center gap-2">
                    <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                    <span>Purchase Bills</span>
                </span>
                <span class="flex items-center gap-1 text-slate-400">
                    <span class="text-[10px] font-bold text-slate-500">{{ $bills->count() }}</span>
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                </span>
            </button>

            <!-- 3. Advance Receives -->
            <button type="button" onclick="openMobileDrilldown('advances')" class="flex w-full items-center justify-between px-3 py-2.5 text-left text-[11px] font-black text-slate-800 hover:bg-slate-50 transition">
                <span class="flex items-center gap-2">
                    <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.948c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75" /></svg>
                    <span>Advance Receives</span>
                </span>
                <span class="flex items-center gap-1 text-slate-400">
                    <span class="text-[10px] font-bold text-slate-500">{{ $advanceReceives->count() }}</span>
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                </span>
            </button>

            <!-- 4. Matching Details -->
            <button type="button" onclick="openMobileDrilldown('matching')" class="flex w-full items-center justify-between px-3 py-2.5 text-left text-[11px] font-black text-slate-800 hover:bg-slate-50 transition">
                <span class="flex items-center gap-2">
                    <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" /></svg>
                    <span>Matching</span>
                </span>
                <span class="flex items-center gap-1 text-slate-400">
                    <span class="text-[10px] font-bold text-emerald-600">{{ $summary['overall_match_pct'] ?? 0 }}%</span>
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                </span>
            </button>

            <!-- 5. Cancelled (if any) -->
            @if ($cancelledPurchases->count() > 0)
                <button type="button" onclick="openMobileDrilldown('cancelled')" class="flex w-full items-center justify-between px-3 py-2.5 text-left text-[11px] font-black text-rose-700 hover:bg-rose-50/50 transition">
                    <span class="flex items-center gap-2">
                        <svg class="h-3.5 w-3.5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span>Cancelled ({{ $cancelledPurchases->count() }})</span>
                    </span>
                    <svg class="h-3.5 w-3.5 text-rose-400" fill="none" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                </button>
            @endif

            <!-- 6. Audit & Lifecycle -->
            <button type="button" onclick="openMobileDrilldown('audit')" class="flex w-full items-center justify-between px-3 py-2.5 text-left text-[11px] font-black text-slate-800 hover:bg-slate-50 transition">
                <span class="flex items-center gap-2">
                    <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span>Audit &amp; Lifecycle</span>
                </span>
                <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
            </button>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- 2. MOBILE DRILLDOWN SLIDE-OVER PANELS (Scoped strictly to current Business Day) -->
<!-- ========================================================================= -->

<!-- Mobile Drilldown: Inventory Details -->
<div id="drilldown-inventory" class="mobile-drilldown fixed inset-0 z-50 bg-slate-900/60 p-0 hidden backdrop-blur-xs">
    <div class="fixed inset-y-0 right-0 w-full max-w-lg bg-slate-50 shadow-2xl flex flex-col overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-4">
            <button type="button" onclick="closeMobileDrilldown('inventory')" class="flex items-center gap-1 text-xs font-black text-slate-700 hover:text-slate-900">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
                <span>Back</span>
            </button>
            <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Inventory Details</h3>
            <span class="text-[10px] font-bold text-slate-400">{{ $comparisonRows->count() }} items</span>
        </div>
        <div class="flex-1 overflow-y-auto p-4 space-y-3">
            @forelse ($comparisonRows as $row)
                @php
                    $adv = (float) ($row['advance_qty'] ?? 0);
                    $bill = (float) ($row['bill_qty'] ?? 0);
                    $matched = (float) ($row['matched_bill_qty'] ?? 0);
                    $pendingAdv = max(0.0, $adv - $matched);
                    $isUnitMismatch = (bool) ($row['unit_mismatch'] ?? false);
                @endphp
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs space-y-2">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <h4 class="text-xs font-black text-slate-900">{{ $row['product_name'] }}</h4>
                            <p class="text-[10px] font-bold text-slate-400">{{ $row['sku'] ?? '' }} · {{ $row['unit'] }}</p>
                        </div>
                        @if ($isUnitMismatch)
                            <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[9px] font-black text-rose-700">Unit Fix</span>
                        @elseif ($pendingAdv > 0.0001)
                            <span class="rounded-full bg-rose-50 px-2 py-0.5 text-[9px] font-black text-rose-600">Pending</span>
                        @else
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[9px] font-black text-emerald-700">Matched</span>
                        @endif
                    </div>
                    <div class="grid grid-cols-3 gap-2 rounded-xl bg-slate-50 p-2.5 text-[11px] font-bold text-slate-700">
                        <div>
                            <span class="block text-[9px] font-black text-slate-400 uppercase">Advance</span>
                            <span>{{ $row['formatted_advance'] }}</span>
                        </div>
                        <div>
                            <span class="block text-[9px] font-black text-slate-400 uppercase">Bill</span>
                            <span>{{ $row['formatted_bill'] }}</span>
                        </div>
                        <div>
                            <span class="block text-[9px] font-black text-slate-400 uppercase">Matched</span>
                            <span class="text-emerald-600">{{ number_format($matched, 2) }} {{ $row['unit'] }}</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between pt-1 text-xs">
                        <span class="font-bold text-slate-500">Inventory Balance:</span>
                        <span class="font-black text-slate-900">{{ $row['formatted_inventory_balance'] ?? $row['formatted_stock_balance'] ?? '--' }}</span>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-xs font-semibold text-slate-400">
                    No products tracked for this day.
                </div>
            @endforelse
        </div>
    </div>
</div>

<!-- Mobile Drilldown: Purchase Bills -->
<div id="drilldown-bills" class="mobile-drilldown fixed inset-0 z-50 bg-slate-900/60 p-0 hidden backdrop-blur-xs">
    <div class="fixed inset-y-0 right-0 w-full max-w-lg bg-slate-50 shadow-2xl flex flex-col overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-4">
            <button type="button" onclick="closeMobileDrilldown('bills')" class="flex items-center gap-1 text-xs font-black text-slate-700 hover:text-slate-900">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
                <span>Back</span>
            </button>
            <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Purchase Bills</h3>
            @if ($day->isOpen() || $day->isReopened())
                <a href="{{ route('purchasing.business-days.bills.create', $day->uuid) }}" class="rounded-xl bg-teal-600 px-3 py-1.5 text-[11px] font-black text-white shadow-xs">
                    + Add
                </a>
            @else
                <span class="text-[10px] font-bold text-slate-400">Closed</span>
            @endif
        </div>
        <div class="flex-1 overflow-y-auto p-4 space-y-3">
            @forelse ($bills as $bill)
                @php $totalQty = $bill->items->sum('received_qty'); @endphp
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs space-y-2">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <h4 class="text-xs font-black text-slate-900">{{ $bill->bill_number ?? $bill->grn_number }}</h4>
                            <p class="text-[11px] font-bold text-slate-600">{{ $bill->purchaseOrder?->supplier?->name ?? 'Direct Supplier' }}</p>
                        </div>
                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[9px] font-black text-emerald-800 uppercase">
                            {{ $bill->status ?? 'Received' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between text-[11px] font-bold text-slate-500 pt-1">
                        <span>{{ $bill->items->count() }} items · {{ number_format($totalQty, 2) }} total qty</span>
                        <span>{{ $bill->received_at?->format('d M, h:i A') ?? '--' }}</span>
                    </div>
                    @if ($day->isOpen() || $day->isReopened())
                        <div class="pt-2 border-t border-slate-100">
                            <a href="{{ route('purchasing.business-days.bills.edit', ['uuid' => $day->uuid, 'grn' => $bill->getRouteKey()]) }}" class="inline-flex w-full items-center justify-center rounded-xl bg-slate-100 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200">
                                Edit Bill
                            </a>
                        </div>
                    @endif
                </div>
            @empty
                <div class="p-8 text-center text-xs font-semibold text-slate-400">
                    No purchase bills recorded for this business day.
                </div>
            @endforelse
        </div>
    </div>
</div>

<!-- Mobile Drilldown: Advance Receives -->
<div id="drilldown-advances" class="mobile-drilldown fixed inset-0 z-50 bg-slate-900/60 p-0 hidden backdrop-blur-xs">
    <div class="fixed inset-y-0 right-0 w-full max-w-lg bg-slate-50 shadow-2xl flex flex-col overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-4">
            <button type="button" onclick="closeMobileDrilldown('advances')" class="flex items-center gap-1 text-xs font-black text-slate-700 hover:text-slate-900">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
                <span>Back</span>
            </button>
            <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Advance Receives</h3>
            <span class="text-[10px] font-bold text-slate-400">{{ $advanceReceives->count() }} GRNs</span>
        </div>
        <div class="flex-1 overflow-y-auto p-4 space-y-3">
            @forelse ($advanceReceives as $adv)
                @php $advTotalQty = $adv->items->sum('received_qty'); @endphp
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs space-y-2">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <h4 class="text-xs font-black text-slate-900">{{ $adv->grn_number }}</h4>
                            <p class="text-[10px] font-semibold text-slate-400">Received {{ $adv->received_at?->format('d M Y, h:i A') }}</p>
                        </div>
                        <span class="rounded-full bg-blue-100 px-2 py-0.5 text-[9px] font-black text-blue-800 uppercase">
                            ADVANCE
                        </span>
                    </div>
                    <div class="space-y-1 pt-1">
                        @foreach ($adv->items as $item)
                            <div class="flex items-center justify-between text-xs font-bold text-slate-700">
                                <span>{{ $item->product?->name ?? 'Item' }}</span>
                                <span>{{ number_format($item->received_qty, 2) }} {{ $item->received_unit ?? $item->product?->unit ?? 'kg' }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-xs font-semibold text-slate-400">
                    No advance receives recorded for this business day.
                </div>
            @endforelse
        </div>
    </div>
</div>

<!-- Mobile Drilldown: Matching Details -->
<div id="drilldown-matching" class="mobile-drilldown fixed inset-0 z-50 bg-slate-900/60 p-0 hidden backdrop-blur-xs">
    <div class="fixed inset-y-0 right-0 w-full max-w-lg bg-slate-50 shadow-2xl flex flex-col overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-4">
            <button type="button" onclick="closeMobileDrilldown('matching')" class="flex items-center gap-1 text-xs font-black text-slate-700 hover:text-slate-900">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
                <span>Back</span>
            </button>
            <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Matching Details</h3>
            <span class="text-[10px] font-black text-emerald-600">{{ $summary['overall_match_pct'] ?? 0 }}% matched</span>
        </div>
        <div class="flex-1 overflow-y-auto p-4 space-y-3">
            @forelse ($comparisonRows as $row)
                @php
                    $adv = (float) ($row['advance_qty'] ?? 0);
                    $bill = (float) ($row['bill_qty'] ?? 0);
                    $matched = (float) ($row['matched_bill_qty'] ?? 0);
                    $pendingAdv = max(0.0, $adv - $matched);
                @endphp
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs space-y-2">
                    <div class="flex items-start justify-between gap-2">
                        <h4 class="text-xs font-black text-slate-900">{{ $row['product_name'] }}</h4>
                        <span class="rounded-full {{ $pendingAdv > 0.0001 ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }} px-2 py-0.5 text-[9px] font-black uppercase">
                            {{ $pendingAdv > 0.0001 ? 'Pending' : 'Matched' }}
                        </span>
                    </div>
                    <div class="grid grid-cols-4 gap-1 rounded-xl bg-slate-50 p-2.5 text-[10px] font-bold text-slate-700 text-center">
                        <div>
                            <span class="block text-[8px] font-black text-slate-400 uppercase">Adv</span>
                            <span>{{ number_format($adv, 1) }}</span>
                        </div>
                        <div>
                            <span class="block text-[8px] font-black text-slate-400 uppercase">Bill</span>
                            <span>{{ number_format($bill, 1) }}</span>
                        </div>
                        <div>
                            <span class="block text-[8px] font-black text-slate-400 uppercase">Matched</span>
                            <span class="text-emerald-600">{{ number_format($matched, 1) }}</span>
                        </div>
                        <div>
                            <span class="block text-[8px] font-black text-slate-400 uppercase">Pending</span>
                            <span class="{{ $pendingAdv > 0 ? 'text-rose-600' : 'text-slate-400' }}">{{ number_format($pendingAdv, 1) }}</span>
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-xs font-semibold text-slate-400">
                    No matching details available.
                </div>
            @endforelse
        </div>
    </div>
</div>

<!-- Mobile Drilldown: Cancelled Purchases -->
@if ($cancelledPurchases->count() > 0)
<div id="drilldown-cancelled" class="mobile-drilldown fixed inset-0 z-50 bg-slate-900/60 p-0 hidden backdrop-blur-xs">
    <div class="fixed inset-y-0 right-0 w-full max-w-lg bg-slate-50 shadow-2xl flex flex-col overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-4">
            <button type="button" onclick="closeMobileDrilldown('cancelled')" class="flex items-center gap-1 text-xs font-black text-slate-700 hover:text-slate-900">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
                <span>Back</span>
            </button>
            <h3 class="text-xs font-black uppercase tracking-wider text-rose-700">Cancelled Purchases</h3>
            <span class="text-[10px] font-bold text-rose-600">{{ $cancelledPurchases->count() }} items</span>
        </div>
        <div class="flex-1 overflow-y-auto p-4 space-y-3">
            @foreach ($cancelledPurchases as $c)
                <div class="rounded-2xl border border-rose-200 bg-white p-4 shadow-xs space-y-2">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <h4 class="text-xs font-black text-slate-900">{{ $c->bill_number ?? $c->grn_number }}</h4>
                            <p class="text-[11px] font-bold text-slate-600">{{ $c->purchaseOrder?->supplier?->name ?? 'Supplier' }}</p>
                        </div>
                        <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[9px] font-black text-rose-800 uppercase">
                            CANCELLED
                        </span>
                    </div>
                    <div class="text-[11px] font-semibold text-slate-600">
                        {{ $c->notes ?? 'Cancelled by purchaser / manager.' }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endif

<!-- Mobile Drilldown: All Pending Items -->
<div id="drilldown-pending" class="mobile-drilldown fixed inset-0 z-50 bg-slate-900/60 p-0 hidden backdrop-blur-xs">
    <div class="fixed inset-y-0 right-0 w-full max-w-lg bg-slate-50 shadow-2xl flex flex-col overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-4">
            <button type="button" onclick="closeMobileDrilldown('pending')" class="flex items-center gap-1 text-xs font-black text-slate-700 hover:text-slate-900">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
                <span>Back</span>
            </button>
            <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">All Pending Work</h3>
            <button type="button" onclick="sharePendingOnWhatsApp()" class="rounded-xl bg-emerald-600 px-2.5 py-1 text-[10px] font-black text-white shadow-xs">
                WhatsApp
            </button>
        </div>
        <div class="flex-1 overflow-y-auto p-4 space-y-3">
            @forelse ($pendingList as $pItem)
                <div class="rounded-2xl border border-rose-200 bg-white p-4 shadow-xs flex items-center justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <h4 class="text-xs font-black text-slate-900">{{ $pItem['product_name'] }}</h4>
                        <p class="text-[11px] font-bold text-rose-600">Pending {{ $pItem['formatted_pending'] }}</p>
                        <p class="text-[10px] font-semibold text-slate-400">Advance: {{ number_format($pItem['advance_qty'], 2) }} · Matched: {{ number_format($pItem['matched_qty'], 2) }}</p>
                    </div>
                    @if ($day->isOpen() || $day->isReopened())
                        <a href="{{ route('purchasing.business-days.bills.create', ['uuid' => $day->uuid, 'product_id' => $pItem['product_id']]) }}" class="inline-flex h-8 items-center justify-center rounded-xl bg-teal-600 px-3 text-[11px] font-black text-white shadow-xs hover:bg-teal-500">
                            Add Bill
                        </a>
                    @endif
                </div>
            @empty
                <div class="p-8 text-center text-xs font-semibold text-slate-400">
                    No pending items.
                </div>
            @endforelse
        </div>
    </div>
</div>

<!-- Mobile Drilldown: Audit Trail -->
<div id="drilldown-audit" class="mobile-drilldown fixed inset-0 z-50 bg-slate-900/60 p-0 hidden backdrop-blur-xs">
    <div class="fixed inset-y-0 right-0 w-full max-w-lg bg-slate-50 shadow-2xl flex flex-col overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-4">
            <button type="button" onclick="closeMobileDrilldown('audit')" class="flex items-center gap-1 text-xs font-black text-slate-700 hover:text-slate-900">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
                <span>Back</span>
            </button>
            <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Audit &amp; Lifecycle</h3>
            <span class="text-[10px] font-bold text-slate-400">Day #{{ $day->id }}</span>
        </div>
        <div class="flex-1 overflow-y-auto p-4 space-y-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs space-y-1">
                <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Opened Event</p>
                <p class="text-xs font-black text-slate-900">{{ $day->openedBy?->name ?? 'System' }}</p>
                <p class="text-[11px] font-semibold text-slate-500">{{ $day->opened_at?->format('d M Y, h:i:s A') }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs space-y-1">
                <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Closed Event</p>
                <p class="text-xs font-black text-slate-900">{{ $day->closedBy?->name ?? 'Not Closed' }}</p>
                <p class="text-[11px] font-semibold text-slate-500">{{ $day->closed_at ? $day->closed_at->format('d M Y, h:i:s A') : '--' }}</p>
                @if ($day->close_note)
                    <div class="mt-2 rounded-xl bg-slate-50 p-2.5 text-xs font-semibold text-slate-700 border border-slate-200">
                        <span class="font-bold">Note:</span> {{ $day->close_note }}
                    </div>
                @endif
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs space-y-1">
                <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Reopened Event</p>
                <p class="text-xs font-black text-slate-900">{{ $day->reopenedBy?->name ?? 'Not Reopened' }}</p>
                <p class="text-[11px] font-semibold text-slate-500">{{ $day->reopened_at ? $day->reopened_at->format('d M Y, h:i:s A') : '--' }}</p>
                @if ($day->reopen_reason)
                    <div class="mt-2 rounded-xl bg-amber-50 p-2.5 text-xs font-semibold text-amber-900 border border-amber-200">
                        <span class="font-bold">Reason:</span> {{ $day->reopen_reason }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Mobile Sticky Bottom Action Bar -->
<div class="fixed inset-x-0 bottom-0 z-40 block lg:hidden border-t border-slate-200 bg-white/95 p-3 backdrop-blur-md pb-[max(env(safe-area-inset-bottom),0.75rem)] shadow-lg">
    <div class="flex items-center gap-2 max-w-lg mx-auto">
        @if ($day->isOpen())
            <a href="{{ route('purchaser.business-days.close.index', $day->uuid) }}" class="flex-1 inline-flex h-12 items-center justify-center rounded-2xl bg-teal-600 px-4 text-sm font-black text-white shadow-md shadow-teal-600/20 active:bg-teal-700 transition">
                Review &amp; Close Day &rarr;
            </a>
        @elseif ($day->isReopened())
            <a href="{{ route('purchaser.business-days.close.index', $day->uuid) }}" class="flex-1 inline-flex h-12 items-center justify-center rounded-2xl bg-amber-600 px-4 text-sm font-black text-white shadow-md shadow-amber-600/20 active:bg-amber-700 transition">
                Review &amp; Close Again &rarr;
            </a>
        @else
            <button type="button" disabled class="flex-1 inline-flex h-12 items-center justify-center rounded-2xl border border-slate-200 bg-slate-100 px-4 text-sm font-bold text-slate-400">
                Business Day Closed
            </button>
            <button type="button" onclick="document.getElementById('reopen-modal').classList.remove('hidden')" class="inline-flex h-12 items-center justify-center rounded-2xl bg-amber-500 px-4 text-xs font-black text-white shadow-md active:bg-amber-600 transition">
                Reopen Day
            </button>
        @endif
    </div>
</div>

<!-- ========================================================================= -->
<!-- 3. DESKTOP VIEW (Complete High-Detail View Preserved Intact) -->
<!-- ========================================================================= -->
<div class="hidden lg:block space-y-6">
    <!-- Active Business Day Header -->
    <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-start gap-4">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl {{ $day->isOpen() ? 'bg-emerald-500 text-white shadow-md shadow-emerald-500/20' : ($day->isReopened() ? 'bg-amber-500 text-white shadow-md shadow-amber-500/20' : 'bg-slate-700 text-white shadow-md shadow-slate-700/20') }}">
                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-black uppercase tracking-[0.2em] text-slate-500">{{ $warehouse->name }}</span>
                        @if ($day->isOpen())
                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-black uppercase tracking-wider text-emerald-800">
                                OPEN
                            </span>
                        @elseif ($day->isReopened())
                            <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-black uppercase tracking-wider text-amber-800">
                                REOPENED
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-black uppercase tracking-wider text-slate-700">
                                CLOSED
                            </span>
                        @endif
                    </div>
                    <h2 class="mt-1 text-2xl font-black text-slate-950">
                        Business Day: {{ $day->business_date->format('d M Y') }}
                    </h2>
                    <p class="mt-0.5 text-xs font-semibold text-slate-500">
                        Opened by {{ $day->openedBy?->name ?? 'System' }} on {{ $day->opened_at?->format('d M Y, h:i A') }}
                        @if ($day->isClosed())
                            · Closed by {{ $day->closedBy?->name ?? 'System' }} on {{ $day->closed_at?->format('d M Y, h:i A') }}
                        @elseif ($day->isReopened())
                            · Reopened by {{ $day->reopenedBy?->name ?? 'System' }} on {{ $day->reopened_at?->format('d M Y, h:i A') }}
                        @endif
                    </p>
                </div>
            </div>

            <!-- Header Actions -->
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('purchasing.business-days.index', ['month' => $day->business_date->format('Y-m'), 'warehouse_id' => $day->warehouse_id, 'view' => 'history']) }}" class="inline-flex h-11 items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 text-xs font-bold text-slate-700 shadow-xs transition hover:bg-slate-50">
                    ← View Previous Business Days
                </a>

                @if ($day->isOpen() || $day->isReopened())
                    <a href="{{ route('purchasing.business-days.bills.create', $day->uuid) }}" class="inline-flex h-11 items-center justify-center rounded-2xl bg-slate-900 px-4 text-xs font-black text-white shadow-sm transition hover:bg-slate-800">
                        + Add Bill
                    </a>
                    <a href="{{ route('purchaser.business-days.close.index', $day->uuid) }}" class="inline-flex h-11 items-center justify-center rounded-2xl bg-teal-600 px-5 text-xs font-black text-white shadow-sm transition hover:bg-teal-500">
                        Verify &amp; Close Day &rarr;
                    </a>
                @else
                    <span class="inline-flex h-11 items-center justify-center rounded-2xl border border-slate-200 bg-slate-100 px-4 text-xs font-bold text-slate-500">
                        Business Day Closed
                    </span>
                    <button type="button" onclick="document.getElementById('reopen-modal').classList.remove('hidden')" class="inline-flex h-11 items-center justify-center rounded-2xl bg-amber-500 px-5 text-xs font-black text-white shadow-sm transition hover:bg-amber-400">
                        Reopen Day
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Top Manager Summary Cards (Canonical) -->
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        <!-- 1. Purchase Bills -->
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Purchase Bills</p>
            <p class="mt-1 text-2xl font-black text-slate-900">{{ $managerSummary['purchase_bills']['count'] ?? $bills->count() }}</p>
            <p class="mt-0.5 text-xs font-semibold text-slate-500">{{ $managerSummary['purchase_bills']['products_count'] ?? 0 }} products</p>
            <div class="mt-2 text-[11px] font-bold text-slate-700 truncate" title="{{ $managerSummary['purchase_bills']['formatted_totals'] ?? '0 kg' }}">
                {{ $managerSummary['purchase_bills']['formatted_totals'] ?? '0 kg' }}
            </div>
        </div>

        <!-- 2. Advance Receives -->
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Advance Receives</p>
            <p class="mt-1 text-2xl font-black text-slate-900">{{ $managerSummary['advance_receives']['count'] ?? $advanceReceives->count() }}</p>
            <p class="mt-0.5 text-xs font-semibold text-slate-500">{{ $managerSummary['advance_receives']['products_count'] ?? 0 }} products</p>
            <div class="mt-2 text-[11px] font-bold text-slate-700 truncate" title="{{ $managerSummary['advance_receives']['formatted_totals'] ?? '0 kg' }}">
                {{ $managerSummary['advance_receives']['formatted_totals'] ?? '0 kg' }}
            </div>
        </div>

        <!-- 3. Products -->
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Products Tracked</p>
            <p class="mt-1 text-2xl font-black text-slate-900">{{ $comparisonRows->count() }}</p>
            <p class="mt-0.5 text-xs font-semibold text-slate-500">Match rate: {{ $summary['overall_match_pct'] ?? 0 }}%</p>
            <div class="mt-2 text-[11px] font-bold text-emerald-600">
                {{ $summary['total_matched_qty'] ?? 0 }} matched
            </div>
        </div>

        <!-- 4. Pending Bills After Match -->
        <div class="rounded-2xl border {{ $pendingList->isNotEmpty() ? 'border-rose-200 bg-rose-50/40' : 'border-slate-200 bg-white' }} p-4 shadow-xs">
            <p class="text-[10px] font-black uppercase tracking-wider {{ $pendingList->isNotEmpty() ? 'text-rose-600' : 'text-slate-400' }}">Pending Bills</p>
            <p class="mt-1 text-2xl font-black {{ $pendingList->isNotEmpty() ? 'text-rose-600' : 'text-slate-900' }}">{{ $pendingList->count() }}</p>
            <p class="mt-0.5 text-xs font-semibold text-slate-500">Pending items</p>
            <div class="mt-2 text-[11px] font-bold text-rose-600 truncate">
                {{ $summary['total_pending_bill_qty'] ?? 0 }} pending qty
            </div>
        </div>

        <!-- 5. Unit Fix Required -->
        <div class="rounded-2xl border {{ ($summary['unit_fix_count'] ?? 0) > 0 ? 'border-rose-200 bg-rose-50/40' : 'border-slate-200 bg-white' }} p-4 shadow-xs">
            <p class="text-[10px] font-black uppercase tracking-wider {{ ($summary['unit_fix_count'] ?? 0) > 0 ? 'text-rose-600' : 'text-slate-400' }}">Unit Fix Required</p>
            <p class="mt-1 text-2xl font-black {{ ($summary['unit_fix_count'] ?? 0) > 0 ? 'text-rose-600' : 'text-slate-900' }}">{{ $summary['unit_fix_count'] ?? 0 }}</p>
            <p class="mt-0.5 text-xs font-semibold text-slate-500">Unit mismatches</p>
            <div class="mt-2 text-[11px] font-bold text-slate-700">
                {{ ($summary['unit_fix_count'] ?? 0) > 0 ? 'Action required' : 'All units valid' }}
            </div>
        </div>

        <!-- 6. Inventory Balance -->
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Inventory Status</p>
            <p class="mt-1 text-2xl font-black text-slate-900">Live</p>
            <p class="mt-0.5 text-xs font-semibold text-slate-500">Realtime Stock</p>
            <div class="mt-2 text-[11px] font-bold text-teal-600">
                Verified On-Hand
            </div>
        </div>
    </div>

    @if ($openCarryForwards->isNotEmpty())
        <!-- Previous Day Pending Tasks (Desktop) -->
        <div class="rounded-2xl border border-teal-200 bg-teal-50/70 p-5 shadow-xs space-y-4">
            <div class="flex items-center justify-between border-b border-teal-200/60 pb-3">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-7 w-7 items-center justify-center rounded-xl bg-teal-600 text-white text-xs font-black">↳</span>
                    <div>
                        <h3 class="text-sm font-black text-teal-950">Previous Day Pending</h3>
                        <p class="text-xs font-semibold text-teal-700">Unresolved carry-forward tasks from older Business Days for {{ $warehouse->name }}</p>
                    </div>
                </div>
                <span class="inline-flex items-center rounded-full bg-teal-100 px-3 py-1 text-xs font-black text-teal-800">
                    {{ $openCarryForwards->count() }} Tasks Outstanding
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach ($openCarryForwards as $cItem)
                    <div class="flex items-center justify-between rounded-2xl border border-teal-100 bg-white p-3.5 shadow-2xs">
                        <div class="min-w-0 flex-1 pr-3">
                            <div class="flex items-center gap-2">
                                <span class="rounded-md bg-teal-100 px-1.5 py-0.5 text-[10px] font-black uppercase text-teal-800">{{ $cItem['origin_date_formatted'] }}</span>
                                <span class="text-xs font-black text-slate-900 truncate">{{ $cItem['product_name'] }}</span>
                            </div>
                            <p class="mt-1 text-xs font-bold text-rose-600">{{ $cItem['formatted_pending'] }} pending</p>
                        </div>
                        <a href="{{ route('purchasing.business-days.bills.create', ['uuid' => $cItem['origin_day_uuid'], 'carry_forward' => $cItem['uuid'], 'product_id' => $cItem['product_id']]) }}"
                           class="inline-flex shrink-0 h-9 items-center justify-center rounded-xl bg-teal-600 px-3.5 text-xs font-black text-white hover:bg-teal-500 shadow-2xs transition">
                            + Add Bill
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Navigation Tabs -->
    <div class="border-b border-slate-200">
        <nav class="flex flex-wrap gap-2" aria-label="Tabs">
            <button type="button" onclick="switchTab('comparison')" id="tab-btn-comparison" class="tab-btn border-b-2 border-teal-600 px-4 py-3 text-xs font-black text-teal-600">
                Daily Comparison Table
            </button>
            <button type="button" onclick="switchTab('pending')" id="tab-btn-pending" class="tab-btn border-b-2 border-transparent px-4 py-3 text-xs font-bold text-slate-500 hover:text-slate-700">
                Pending Bill Worklist
                @if ($pendingList->isNotEmpty())
                    <span class="ml-1.5 rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-black text-rose-700">{{ $pendingList->count() }}</span>
                @endif
            </button>
            <button type="button" onclick="switchTab('bills')" id="tab-btn-bills" class="tab-btn border-b-2 border-transparent px-4 py-3 text-xs font-bold text-slate-500 hover:text-slate-700">
                Recorded Purchase Bills
                <span class="ml-1.5 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-700">{{ $bills->count() }}</span>
            </button>
            <button type="button" onclick="switchTab('audit')" id="tab-btn-audit" class="tab-btn border-b-2 border-transparent px-4 py-3 text-xs font-bold text-slate-500 hover:text-slate-700">
                Audit &amp; History
            </button>
        </nav>
    </div>

    <!-- TAB 1: Comparison Table -->
    <div id="tab-panel-comparison" class="tab-panel space-y-4">
        <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-4 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-black text-slate-950">Advance vs Bill Comparison</h3>
                    <p class="text-xs font-semibold text-slate-400">Scoped to Business Day #{{ $day->id }} · {{ $warehouse->name }}</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50/80 text-[11px] font-black uppercase tracking-[0.14em] text-slate-500">
                            <th class="py-3.5 px-6">Product</th>
                            <th class="py-3.5 px-4 text-right">Advance</th>
                            <th class="py-3.5 px-4 text-right">Bill</th>
                            <th class="py-3.5 px-4 text-right">Matched</th>
                            <th class="py-3.5 px-4 text-right">Pending Bill</th>
                            <th class="py-3.5 px-4 text-right">Diff</th>
                            <th class="py-3.5 px-4 text-right">Inventory Balance</th>
                            <th class="py-3.5 px-6 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs font-semibold text-slate-700">
                        @forelse ($comparisonRows as $row)
                            @php
                                $adv = (float) ($row['advance_qty'] ?? 0);
                                $bill = (float) ($row['bill_qty'] ?? 0);
                                $matched = (float) ($row['matched_bill_qty'] ?? 0);
                                $pendingAdv = max(0.0, $adv - $matched);
                                $isUnitMismatch = (bool) ($row['unit_mismatch'] ?? false);
                            @endphp
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="py-4 px-6 font-black text-slate-900">
                                    <div>
                                        <span>{{ $row['product_name'] }}</span>
                                        <span class="block text-[10px] font-bold text-slate-400">{{ $row['sku'] ?? $row['product_code'] ?? '' }} · {{ $row['unit'] }}</span>
                                    </div>
                                </td>
                                <td class="py-4 px-4 text-right font-bold text-slate-900">{{ $row['formatted_advance'] }}</td>
                                <td class="py-4 px-4 text-right font-bold text-slate-900">{{ $row['formatted_bill'] }}</td>
                                <td class="py-4 px-4 text-right font-bold text-emerald-600">{{ number_format($matched, 2) }} {{ $row['unit'] }}</td>
                                <td class="py-4 px-4 text-right">
                                    @if ($pendingAdv > 0.0001)
                                        <span class="font-black text-rose-600">{{ number_format($pendingAdv, 2) }} {{ $row['unit'] }}</span>
                                    @else
                                        <span class="text-slate-400">0 {{ $row['unit'] }}</span>
                                    @endif
                                </td>
                                <td class="py-4 px-4 text-right font-bold {{ (float) ($row['diff'] ?? 0) > 0 ? 'text-amber-600' : 'text-slate-600' }}">
                                    {{ $row['formatted_diff'] ?? '--' }}
                                </td>
                                <td class="py-4 px-4 text-right font-bold text-slate-800">
                                    {{ $row['formatted_inventory_balance'] ?? $row['formatted_stock_balance'] ?? '--' }}
                                </td>
                                <td class="py-4 px-6 text-center whitespace-nowrap">
                                    @if ($isUnitMismatch)
                                        <span class="inline-flex items-center rounded-full bg-rose-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-rose-700">
                                            Unit Fix
                                        </span>
                                    @elseif ($pendingAdv > 0.0001)
                                        <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-rose-600">
                                            Pending Bill
                                        </span>
                                    @elseif ($bill > $adv && $adv > 0)
                                        <span class="inline-flex items-center rounded-full bg-sky-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-sky-700">
                                            Excess Bill
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-emerald-700">
                                            Matched
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-12 text-center text-sm font-semibold text-slate-400">
                                    No goods received or bills recorded for this business day.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 2: Pending Bill View (Main Purchaser Work List with Multi-Select) -->
    <div id="tab-panel-pending" class="tab-panel hidden space-y-4">
        <form method="GET" action="{{ route('purchasing.business-days.bills.create', $day->uuid) }}" id="multi-pending-bill-form">
            <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-base font-black text-slate-950">Pending Bill Work List</h3>
                        <p class="text-xs font-semibold text-slate-400">Advance items awaiting vendor bill entry · Pending = Advance - Matched Qty</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" onclick="sharePendingOnWhatsApp()" class="inline-flex h-9 items-center justify-center rounded-xl bg-emerald-600 px-3 text-xs font-black text-white shadow-xs transition hover:bg-emerald-500">
                            Share Pending (WhatsApp)
                        </button>
                        @if ($day->isOpen() || $day->isReopened())
                            <button type="submit" class="inline-flex h-9 items-center justify-center rounded-xl bg-teal-600 px-4 text-xs font-black text-white shadow-xs transition hover:bg-teal-500">
                                Create Bill for Selected Items
                            </button>
                            <a href="{{ route('purchasing.business-days.bills.create', $day->uuid) }}" class="inline-flex h-9 items-center justify-center rounded-xl bg-slate-900 px-3 text-xs font-black text-white shadow-xs transition hover:bg-slate-800">
                                + Add Bill
                            </a>
                        @endif
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/80 text-[11px] font-black uppercase tracking-[0.14em] text-slate-500">
                                <th class="py-3.5 px-4 text-center w-12">
                                    <input type="checkbox" id="select-all-pending" onchange="toggleSelectAll(this)" class="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                                </th>
                                <th class="py-3.5 px-6">Product</th>
                                <th class="py-3.5 px-4 text-right">Advance Received</th>
                                <th class="py-3.5 px-4 text-right">Matched</th>
                                <th class="py-3.5 px-4 text-right">Pending Bill Qty</th>
                                <th class="py-3.5 px-6 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs font-semibold text-slate-700">
                            @forelse ($pendingList as $item)
                                <tr class="hover:bg-slate-50/60 transition-colors">
                                    <td class="py-4 px-4 text-center">
                                        <input type="checkbox" name="product_ids[]" value="{{ $item['product_id'] }}" class="pending-item-checkbox h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                                    </td>
                                    <td class="py-4 px-6 font-black text-slate-900">
                                        <span>{{ $item['product_name'] }}</span>
                                        <span class="block text-[10px] font-bold text-slate-400">{{ $item['sku'] }}</span>
                                    </td>
                                    <td class="py-4 px-4 text-right font-bold text-slate-700">{{ number_format($item['advance_qty'], 2) }} {{ $item['unit'] }}</td>
                                    <td class="py-4 px-4 text-right font-bold text-emerald-600">{{ number_format($item['matched_qty'], 2) }} {{ $item['unit'] }}</td>
                                    <td class="py-4 px-4 text-right font-black text-rose-600 text-sm">
                                        {{ $item['formatted_pending'] }}
                                    </td>
                                    <td class="py-4 px-6 text-center whitespace-nowrap">
                                        @if ($day->isOpen() || $day->isReopened())
                                            <a href="{{ route('purchasing.business-days.bills.create', ['uuid' => $day->uuid, 'product_id' => $item['product_id']]) }}" class="inline-flex items-center justify-center rounded-xl bg-teal-600 px-3 py-1.5 text-xs font-black text-white hover:bg-teal-500 shadow-xs transition">
                                                Add Bill
                                            </a>
                                        @else
                                            <span class="text-slate-400 font-bold">Day Closed</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-12 text-center text-sm font-semibold text-emerald-600">
                                        🎉 Clean state! All advance receipts for this business day have matching supplier bills.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </form>
    </div>

    <!-- TAB 3: Recorded Purchase Bills List -->
    <div id="tab-panel-bills" class="tab-panel hidden space-y-4">
        <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-4 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-black text-slate-950">Recorded Purchase Bills</h3>
                    <p class="text-xs font-semibold text-slate-400">Supplier bills associated with this business day.</p>
                </div>
                @if ($day->isOpen() || $day->isReopened())
                    <a href="{{ route('purchasing.business-days.bills.create', $day->uuid) }}" class="inline-flex h-9 items-center justify-center rounded-xl bg-slate-900 px-3 text-xs font-black text-white shadow-xs transition hover:bg-slate-800">
                        + Add Bill
                    </a>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50/80 text-[11px] font-black uppercase tracking-[0.14em] text-slate-500">
                            <th class="py-3.5 px-6">Bill / Invoice #</th>
                            <th class="py-3.5 px-4">Supplier</th>
                            <th class="py-3.5 px-4">Received Timestamp</th>
                            <th class="py-3.5 px-4 text-center">Items</th>
                            <th class="py-3.5 px-4 text-right">Total Qty</th>
                            <th class="py-3.5 px-6 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs font-semibold text-slate-700">
                        @forelse ($bills as $bill)
                            @php
                                $totalQty = $bill->items->sum('received_qty');
                            @endphp
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="py-4 px-6 font-black text-slate-900">
                                    <span>{{ $bill->bill_number ?? $bill->grn_number }}</span>
                                    <span class="block text-[10px] font-bold text-slate-400">{{ $bill->grn_number }}</span>
                                </td>
                                <td class="py-4 px-4 font-bold text-slate-800">
                                    {{ $bill->purchaseOrder?->supplier?->name ?? 'Direct Supplier' }}
                                </td>
                                <td class="py-4 px-4 text-slate-600">
                                    {{ $bill->received_at?->format('d M Y, h:i A') ?? '--' }}
                                </td>
                                <td class="py-4 px-4 text-center">
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-700">
                                        {{ $bill->items->count() }}
                                    </span>
                                </td>
                                <td class="py-4 px-4 text-right font-black text-slate-900">
                                    {{ number_format($totalQty, 2) }}
                                </td>
                                <td class="py-4 px-6 text-center whitespace-nowrap">
                                    @if ($day->isOpen() || $day->isReopened())
                                        <a href="{{ route('purchasing.business-days.bills.edit', ['uuid' => $day->uuid, 'grn' => $bill->getRouteKey()]) }}" class="inline-flex items-center justify-center rounded-xl bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-200 transition">
                                            Edit Bill
                                        </a>
                                    @else
                                        <span class="text-slate-400 font-bold" title="Day is closed. Reopen to edit bills.">Edit Disabled</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-12 text-center text-sm font-semibold text-slate-400">
                                    No purchase bills recorded for this business day.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 4: Audit & History -->
    <div id="tab-panel-audit" class="tab-panel hidden space-y-4">
        <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm space-y-6">
            <div>
                <h3 class="text-base font-black text-slate-950">Business Day Lifecycle Audit</h3>
                <p class="text-xs font-semibold text-slate-400">Strict chronological audit trail of opening, verification, close, and reopen events.</p>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4 space-y-1">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Opened Event</p>
                    <p class="text-sm font-black text-slate-900">{{ $day->openedBy?->name ?? 'System' }}</p>
                    <p class="text-xs font-semibold text-slate-500">{{ $day->opened_at?->format('d M Y, h:i:s A') }}</p>
                </div>

                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4 space-y-1">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Closed Event</p>
                    <p class="text-sm font-black text-slate-900">{{ $day->closedBy?->name ?? 'Not Closed' }}</p>
                    <p class="text-xs font-semibold text-slate-500">{{ $day->closed_at ? $day->closed_at->format('d M Y, h:i:s A') : '--' }}</p>
                    @if ($day->close_note)
                        <div class="mt-2 rounded-xl bg-white p-2.5 text-xs font-semibold text-slate-700 border border-slate-200">
                            <span class="font-bold text-slate-900">Close Note:</span> {{ $day->close_note }}
                        </div>
                    @endif
                </div>

                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4 space-y-1">
                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Reopened Event</p>
                    <p class="text-sm font-black text-slate-900">{{ $day->reopenedBy?->name ?? 'Not Reopened' }}</p>
                    <p class="text-xs font-semibold text-slate-500">{{ $day->reopened_at ? $day->reopened_at->format('d M Y, h:i:s A') : '--' }}</p>
                    @if ($day->reopen_reason)
                        <div class="mt-2 rounded-xl bg-white p-2.5 text-xs font-semibold text-amber-900 border border-amber-200 bg-amber-50">
                            <span class="font-bold">Reopen Reason:</span> {{ $day->reopen_reason }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Verify & Close Day (Shared between Mobile Sticky Bar & Desktop Header) -->
<div id="verify-close-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs hidden">
    <div class="w-full max-w-lg overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5">
            <div>
                <h3 class="text-base font-black text-slate-950">Verify &amp; Close Business Day</h3>
                <p class="text-xs font-semibold text-slate-400">{{ $warehouse->name }} · {{ $day->business_date->format('d M Y') }}</p>
            </div>
            <button type="button" onclick="document.getElementById('verify-close-modal').classList.add('hidden')" class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>

        <form method="POST" action="{{ route('purchasing.business-days.verify-close', $day->uuid) }}" class="p-6 space-y-5">
            @csrf
            <!-- Verification Checklist Metrics -->
            <div class="space-y-2 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-xs font-bold text-slate-700">
                <div class="flex justify-between py-1 border-b border-slate-200/60">
                    <span>Purchase Bills Recorded:</span>
                    <span class="text-slate-900 font-black">{{ $managerSummary['purchase_bills']['count'] ?? $bills->count() }}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-200/60">
                    <span>Advance GRNs Recorded:</span>
                    <span class="text-slate-900 font-black">{{ $managerSummary['advance_receives']['count'] ?? $advanceReceives->count() }}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-200/60">
                    <span>Total Products Tracked:</span>
                    <span class="text-slate-900 font-black">{{ $comparisonRows->count() }}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-200/60">
                    <span>Pending Products (Unmatched):</span>
                    <span class="{{ $pendingList->isNotEmpty() ? 'text-rose-600' : 'text-emerald-600' }} font-black">{{ $pendingList->count() }}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-200/60">
                    <span>Unit Mismatches:</span>
                    <span class="{{ ($summary['unit_fix_count'] ?? 0) > 0 ? 'text-rose-600' : 'text-emerald-600' }} font-black">{{ $summary['unit_fix_count'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between py-1">
                    <span>Inventory Verification:</span>
                    <span class="text-emerald-600 font-black">Passed ✓</span>
                </div>
            </div>

            @if ($pendingList->isEmpty() && ($summary['unit_fix_count'] ?? 0) === 0)
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-xs font-bold text-emerald-800">
                    <p class="font-black text-sm text-emerald-900">Everything Complete ✓</p>
                    <p class="mt-1">All advance receipts have matched supplier bills and there are zero unit anomalies. This business day is clean.</p>
                </div>
            @else
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs font-semibold text-amber-900 space-y-2">
                    <div class="flex items-center justify-between border-b border-amber-200/60 pb-2">
                        <p class="font-black text-sm">Pending Bills: {{ $pendingList->count() }}</p>
                        <span class="rounded-full bg-amber-200 px-2.5 py-0.5 text-[10px] font-black text-amber-900">{{ $pendingList->count() }} Items</span>
                    </div>
                    <div class="divide-y divide-amber-200/60 max-h-40 overflow-y-auto pr-1">
                        @foreach ($pendingList as $pItem)
                            <div class="flex items-center justify-between py-1.5 text-xs font-bold">
                                <span class="text-amber-950">{{ $pItem['product_name'] }}</span>
                                <span class="font-black text-rose-700">{{ $pItem['formatted_pending'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label for="close_note" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Reason / Note</label>
                    <textarea id="close_note" name="close_note" rows="2" placeholder="Optional or required note for close..." class="mt-1.5 w-full rounded-2xl border border-slate-200 bg-slate-50 p-3 text-xs font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none"></textarea>
                </div>
            @endif

            <div class="pt-2 flex flex-wrap items-center justify-end gap-2.5">
                <button type="button" onclick="document.getElementById('verify-close-modal').classList.add('hidden')" class="rounded-2xl border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50">
                    Cancel
                </button>
                @if ($pendingList->isEmpty() && ($summary['unit_fix_count'] ?? 0) === 0)
                    <button type="submit" name="close_mode" value="clean" class="rounded-2xl bg-teal-600 px-5 py-2.5 text-xs font-black text-white hover:bg-teal-500 shadow-sm">
                        Verify &amp; Close Day
                    </button>
                @else
                    @if ($warehouseSettings['allow_carry_forward'] ?? true)
                        <button type="submit" name="close_mode" value="carry_forward" class="rounded-2xl bg-teal-600 px-5 py-2.5 text-xs font-black text-white hover:bg-teal-500 shadow-sm">
                            Close &amp; Carry Pending Forward
                        </button>
                    @endif
                    @if ($warehouseSettings['allow_close_with_pending'] ?? true)
                        <button type="submit" name="close_mode" value="with_pending" class="rounded-2xl bg-amber-600 px-4 py-2.5 text-xs font-black text-white hover:bg-amber-500 shadow-sm">
                            Close With Pending
                        </button>
                    @endif
                @endif
            </div>
        </form>
    </div>
</div>

<!-- Modal: Reopen Day (Shared between Mobile & Desktop) -->
<div id="reopen-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs hidden">
    <div class="w-full max-w-md overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5">
            <div>
                <h3 class="text-base font-black text-slate-950">Reopen Business Day</h3>
                <p class="text-xs font-semibold text-slate-400">{{ $warehouse->name }} · {{ $day->business_date->format('d M Y') }}</p>
            </div>
            <button type="button" onclick="document.getElementById('reopen-modal').classList.add('hidden')" class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>

        <form method="POST" action="{{ route('purchasing.business-days.reopen', $day->uuid) }}" class="p-6 space-y-4">
            @csrf
            <div>
                <label for="reopen_reason" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Reopen Reason (Mandatory Audit)</label>
                <textarea id="reopen_reason" name="reopen_reason" rows="3" required placeholder="Explain why this day is being reopened (e.g. late fruit bill arrived)..." class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 p-3 text-xs font-bold text-slate-900 focus:border-amber-500 focus:bg-white focus:outline-none"></textarea>
            </div>

            <p class="text-[11px] font-semibold text-slate-500">
                After reopening, bill entry and modifications will be re-enabled. Auto-match will rerun and the day must be verified and closed again.
            </p>

            <div class="pt-3 flex items-center justify-end gap-3">
                <button type="button" onclick="document.getElementById('reopen-modal').classList.add('hidden')" class="rounded-2xl border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50">
                    Cancel
                </button>
                <button type="submit" class="rounded-2xl bg-amber-600 px-5 py-2.5 text-xs font-black text-white hover:bg-amber-500 shadow-sm">
                    Confirm Reopen
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openMobileDrilldown(name) {
        const el = document.getElementById('drilldown-' + name);
        if (el) {
            el.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }
    }

    function closeMobileDrilldown(name) {
        const el = document.getElementById('drilldown-' + name);
        if (el) {
            el.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }
    }

    function switchTab(tabName) {
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.className = 'tab-btn border-b-2 border-transparent px-4 py-3 text-xs font-bold text-slate-500 hover:text-slate-700';
        });
        document.querySelectorAll('.tab-panel').forEach(panel => {
            panel.classList.add('hidden');
        });

        const activeBtn = document.getElementById('tab-btn-' + tabName);
        const activePanel = document.getElementById('tab-panel-' + tabName);
        if (activeBtn && activePanel) {
            activeBtn.className = 'tab-btn border-b-2 border-teal-600 px-4 py-3 text-xs font-black text-teal-600';
            activePanel.classList.remove('hidden');
        }
    }

    function sharePendingOnWhatsApp() {
        const warehouseName = @json($warehouse->name);
        const businessDate = @json($day->business_date->format('d M Y'));
        const pendingItems = @json($pendingList);

        if (!pendingItems.length) {
            alert('No pending bills for this business day.');
            return;
        }

        let message = `*Pending Bills Report*\nWarehouse: ${warehouseName}\nBusiness Day: ${businessDate}\n\n`;
        pendingItems.forEach((item, index) => {
            message += `${index + 1}. *${item.product_name}*: ${item.formatted_pending}\n`;
        });

        message += `\nPlease provide supplier bills for the above items.`;

        const encoded = encodeURIComponent(message);
        window.open(`https://api.whatsapp.com/send?text=${encoded}`, '_blank');
    }

    function toggleSelectAll(selectAllCheckbox) {
        const checkboxes = document.querySelectorAll('.pending-item-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = selectAllCheckbox.checked;
        });
    }

    // Auto-open verify & close modal if URL has hash
    if (window.location.hash === '#verify-close') {
        const modal = document.getElementById('verify-close-modal');
        if (modal) modal.classList.remove('hidden');
    }
</script>
@endsection
