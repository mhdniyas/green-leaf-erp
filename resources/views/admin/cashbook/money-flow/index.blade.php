@extends('admin.cashbook.layouts.app')

@php
    $scope = $scope ?? 'all';
    $scopeTitle = $scopeTitle ?? 'All Shops';
    $scopeSubtitle = $scopeSubtitle ?? 'Unified real-time company money position & retail collection flows';
    $formRoute = $formRoute ?? route('admin.cashbook.money-flow');
    $currentRouteName = $currentRouteName ?? 'admin.cashbook.money-flow';
    $routeParams = $routeParams ?? [];
@endphp

@section('title', $scopeTitle.' — Money Flow')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 pb-12">

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-3xl border border-slate-200 shadow-sm">
        <div>
            <div class="flex items-center gap-2">
                <span class="p-2 rounded-2xl bg-emerald-50 text-emerald-700 border border-emerald-200">
                    <i data-lucide="{{ $scope === 'client' ? 'building-2' : ($scope === 'direct' ? 'store' : 'activity') }}" class="w-5 h-5"></i>
                </span>
                <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">{{ $scopeTitle }}</h1>
                @if($scope === 'client')
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800 border border-emerald-200">
                        Client Scope
                    </span>
                @elseif($scope === 'direct')
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-sky-100 text-sky-800 border border-sky-200">
                        Direct Shops
                    </span>
                @endif
            </div>
            <p class="text-xs text-slate-500 font-medium mt-1">
                {{ $scopeSubtitle }} for 
                <span class="font-bold text-slate-800 font-mono">{{ \Carbon\Carbon::parse($businessDate)->format('d M Y') }}</span>
            </p>
        </div>

        <!-- Date & Filter Controls -->
        <form method="GET" action="{{ $formRoute }}" id="money-flow-filter-form" class="flex items-center gap-2 flex-wrap">
            <input type="hidden" name="status" value="{{ $selectedStatus }}">
            <input type="hidden" name="shop_id" value="{{ $selectedShopId }}">
            <input type="hidden" name="calendar_month" value="{{ $calendarData['calendar_month'] ?? '' }}">

            <!-- Quick Day Buttons -->
            <div class="inline-flex rounded-xl bg-slate-100 p-1 border border-slate-200 text-xs font-bold">
                <a href="{{ route($currentRouteName, array_merge($routeParams, ['date' => today()->toDateString(), 'shop_id' => $selectedShopId, 'status' => $selectedStatus, 'calendar_month' => $calendarData['calendar_month'] ?? null])) }}"
                   class="px-3 py-1.5 rounded-lg transition {{ $businessDate === today()->toDateString() ? 'bg-white text-emerald-800 shadow-xs font-extrabold' : 'text-slate-600 hover:text-slate-900' }}">
                    Today
                </a>
                <a href="{{ route($currentRouteName, array_merge($routeParams, ['date' => today()->subDay()->toDateString(), 'shop_id' => $selectedShopId, 'status' => $selectedStatus, 'calendar_month' => $calendarData['calendar_month'] ?? null])) }}"
                   class="px-3 py-1.5 rounded-lg transition {{ $businessDate === today()->subDay()->toDateString() ? 'bg-white text-emerald-800 shadow-xs font-extrabold' : 'text-slate-600 hover:text-slate-900' }}">
                    Yesterday
                </a>
            </div>

            <div class="flex items-center gap-1.5 bg-slate-100 px-3 py-1.5 rounded-xl border border-slate-200">
                <i data-lucide="calendar" class="w-4 h-4 text-slate-500"></i>
                <input type="date" name="date" value="{{ $businessDate }}" onchange="document.getElementById('money-flow-filter-form').submit()"
                       class="bg-transparent text-xs font-mono font-bold text-slate-800 border-none focus:outline-none cursor-pointer">
            </div>

            <!-- Calendar Shortcut on Top -->
            <a href="#monthly-pending-calendar"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold border border-slate-200 transition"
               title="Jump to Monthly Pending Calendar">
                <i data-lucide="calendar-days" class="w-4 h-4 text-emerald-700"></i>
                <span>Calendar</span>
            </a>
        </form>
    </div>

    <!-- Position KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        
        <!-- 1. COMPANY RECEIVED -->
        <div class="bg-white p-5 rounded-3xl border border-emerald-100 shadow-sm relative overflow-hidden flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-emerald-700">Company Received</span>
                <span class="p-2 rounded-xl bg-emerald-50 text-emerald-600">
                    <i data-lucide="badge-check" class="w-4 h-4"></i>
                </span>
            </div>
            <div class="mt-3">
                <div class="text-2xl sm:text-3xl font-black text-slate-900 font-mono tracking-tight">
                    ₹{{ number_format($summary['verified_company_money'] ?? 0, 2) }}
                </div>
                <div class="flex items-center gap-1.5 text-[11px] font-bold text-emerald-700 mt-1">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    Verified in bank &amp; cash box
                </div>
            </div>
        </div>

        <!-- 2. NEEDS ATTENTION -->
        <div class="bg-white p-5 rounded-3xl border border-amber-100 shadow-sm relative overflow-hidden flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-amber-700">Needs Attention</span>
                <span class="p-2 rounded-xl bg-amber-50 text-amber-600">
                    <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                </span>
            </div>
            <div class="mt-3">
                <div class="text-2xl sm:text-3xl font-black text-slate-900 font-mono tracking-tight">
                    ₹{{ number_format($summary['pending_verification_total'] ?? 0, 2) }}
                </div>
                <div class="flex items-center gap-1.5 text-[11px] font-bold text-amber-700 mt-1">
                    <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                    {{ $summary['pending_verification_count'] ?? 0 }} pending verifications
                </div>
            </div>
        </div>

        <!-- 3. CASH WITH SHOPS -->
        <div class="bg-white p-5 rounded-3xl border border-sky-100 shadow-sm relative overflow-hidden flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-sky-700">Cash With Shops</span>
                <span class="p-2 rounded-xl bg-sky-50 text-sky-600">
                    <i data-lucide="store" class="w-4 h-4"></i>
                </span>
            </div>
            <div class="mt-3">
                <div class="text-2xl sm:text-3xl font-black text-slate-900 font-mono tracking-tight">
                    ₹{{ number_format($summary['cash_with_shops']['total_cash_with_shops'] ?? 0, 2) }}
                </div>
                <div class="flex items-center gap-1.5 text-[11px] font-bold text-sky-700 mt-1">
                    <span class="w-2 h-2 rounded-full bg-sky-500"></span>
                    Retained physical retail cash
                </div>
            </div>
        </div>

        <!-- 4. FLOATING CHEQUES -->
        <div class="bg-white p-5 rounded-3xl border border-purple-100 shadow-sm relative overflow-hidden flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-purple-700">Floating Cheques</span>
                <span class="p-2 rounded-xl bg-purple-50 text-purple-600">
                    <i data-lucide="clock" class="w-4 h-4"></i>
                </span>
            </div>
            <div class="mt-3">
                <div class="text-2xl sm:text-3xl font-black text-slate-900 font-mono tracking-tight">
                    ₹{{ number_format($summary['floating_cheques']['total_floating'] ?? 0, 2) }}
                </div>
                <div class="flex items-center gap-1.5 text-[11px] font-bold text-purple-700 mt-1">
                    <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                    {{ $summary['floating_cheques']['floating_count'] ?? 0 }} uncleared cheques
                </div>
            </div>
        </div>

    </div>

    <!-- Shop Tabs Filter -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 custom-scrollbar">
        <a href="{{ route($currentRouteName, array_merge($routeParams, ['date' => $businessDate, 'shop_id' => null, 'status' => $selectedStatus, 'calendar_month' => $calendarData['calendar_month'] ?? null])) }}"
           class="px-4 py-2 rounded-2xl text-xs font-extrabold transition-all whitespace-nowrap {{ is_null($selectedShopId) ? 'bg-slate-900 text-white shadow-xs' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}">
            All {{ $scopeTitle }}
        </a>
        @foreach($shops as $sh)
            <a href="{{ route($currentRouteName, array_merge($routeParams, ['date' => $businessDate, 'shop_id' => $sh->shop_id, 'status' => $selectedStatus, 'calendar_month' => $calendarData['calendar_month'] ?? null])) }}"
               class="px-4 py-2 rounded-2xl text-xs font-extrabold transition-all whitespace-nowrap {{ (int) $selectedShopId === (int) $sh->shop_id ? 'bg-slate-900 text-white shadow-xs' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}">
                {{ $sh->name }}
            </a>
        @endforeach
    </div>

    <!-- Status Tabs Filter -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 custom-scrollbar">
        @php
            $statusTabs = [
                'all' => 'All Collections',
                'needs_attention' => 'Needs Attention / Unverified',
                'verified' => 'Company Received',
                'cash_with_shop' => 'Cash With Shop',
                'floating' => 'Floating Cheques',
            ];
        @endphp
        @foreach($statusTabs as $k => $label)
            <a href="{{ route($currentRouteName, array_merge($routeParams, ['date' => $businessDate, 'shop_id' => $selectedShopId, 'status' => $k, 'calendar_month' => $calendarData['calendar_month'] ?? null])) }}"
               class="px-3.5 py-1.5 rounded-xl text-xs font-extrabold transition-all whitespace-nowrap {{ $selectedStatus === $k ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <!-- ── SHOP MONEY FLOW TABLE-BASED OVERVIEW ────────────────────────── -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden space-y-0">
        <div class="p-5 sm:p-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50/50">
            <div>
                <h2 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                    <i data-lucide="store" class="w-4 h-4 text-emerald-600"></i>
                    <span>{{ $scopeTitle }} Overview</span>
                    <span class="text-xs text-slate-400 font-mono font-normal">({{ count($shopCards) }} {{ Str::plural('shop', count($shopCards)) }})</span>
                    <span class="sr-only">Shop Summary Positions</span>
                </h2>
                <p class="text-xs text-slate-500 font-medium mt-0.5">
                    Shop Summary Positions &amp; real-time collection breakdown, physical cash positions, floating cheques, and attention statuses.
                </p>
            </div>
            @if($scope === 'all' && count($shopCards) > 0)
                <div class="flex items-center gap-2 text-xs font-bold text-slate-500">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-slate-100 text-slate-700">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        {{ count(array_filter($shopCards, fn($c) => !empty($c['client_name']))) }} Client Shops
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-slate-100 text-slate-700">
                        <span class="w-2 h-2 rounded-full bg-sky-500"></span>
                        {{ count(array_filter($shopCards, fn($c) => empty($c['client_name']))) }} Direct Shops
                    </span>
                </div>
            @endif
        </div>

        @if(empty($shopCards))
            <div class="p-12 text-center">
                <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
                    <i data-lucide="store" class="w-6 h-6"></i>
                </div>
                <p class="text-sm text-slate-700 font-bold">No active shops found matching the selected filter.</p>
                <p class="text-xs text-slate-400 mt-1">Try resetting the status filter or choosing another day.</p>
            </div>
        @else
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50/80 text-[11px] font-extrabold uppercase tracking-wider text-slate-600">
                            <th scope="col" class="py-3.5 pl-6 pr-4">Shop</th>
                            <th scope="col" class="py-3.5 px-4 text-right">Received</th>
                            <th scope="col" class="py-3.5 px-4 text-right">Cash</th>
                            <th scope="col" class="py-3.5 px-4 text-right">Cheques</th>
                            <th scope="col" class="py-3.5 px-4 text-center">Attention</th>
                            <th scope="col" class="py-3.5 px-4 text-right">Outstanding</th>
                            <th scope="col" class="py-3.5 pl-4 pr-6 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($shopCards as $card)
                            @php
                                $statusBadgeClass = match($card['status_key']) {
                                    'needs_attention' => 'bg-rose-50 text-rose-800 border-rose-200',
                                    'needs_acceptance' => 'bg-amber-50 text-amber-800 border-amber-200',
                                    'pending_verification' => 'bg-sky-50 text-sky-800 border-sky-200',
                                    default => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                };
                            @endphp
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <!-- 1. Shop Column -->
                                <td class="py-3.5 pl-6 pr-4">
                                    <div class="flex items-center gap-2">
                                        <span class="font-black text-sm text-slate-900">{{ $card['shop_name'] }}</span>
                                        @if(!empty($card['shop_code']))
                                            <span class="px-1.5 py-0.5 rounded-md bg-slate-100 text-[10px] font-bold text-slate-600 border border-slate-200 font-mono">
                                                {{ $card['shop_code'] }}
                                            </span>
                                        @endif
                                        @if($scope === 'all' && !empty($card['client_name']))
                                            <span class="px-1.5 py-0.5 rounded-md bg-emerald-50 text-[10px] font-extrabold text-emerald-700 border border-emerald-200">
                                                {{ $card['client_name'] }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-[11px] text-slate-400 font-mono mt-0.5">
                                        Total Collection: ₹{{ number_format($card['total_collection'], 2) }}
                                    </div>
                                </td>

                                <!-- 2. Received Column -->
                                <td class="py-3.5 px-4 text-right">
                                    <div class="font-mono font-bold text-sm {{ $card['company_received'] > 0 ? 'text-emerald-700' : 'text-slate-800' }}">
                                        ₹{{ number_format($card['company_received'], 2) }}
                                    </div>
                                    <div class="text-[10px] text-slate-400 font-medium">Verified in bank/box</div>
                                </td>

                                <!-- 3. Cash Column -->
                                <td class="py-3.5 px-4 text-right">
                                    <div class="font-mono font-bold text-sm {{ $card['cash_with_shop'] > 0 ? 'text-sky-700' : 'text-slate-800' }}">
                                        ₹{{ number_format($card['cash_with_shop'], 2) }}
                                    </div>
                                    <div class="text-[10px] text-slate-400 font-medium">With shop</div>
                                </td>

                                <!-- 4. Cheques Column -->
                                <td class="py-3.5 px-4 text-right">
                                    <div class="font-mono font-bold text-sm {{ $card['floating_cheques'] > 0 ? 'text-purple-700' : 'text-slate-800' }}">
                                        ₹{{ number_format($card['floating_cheques'], 2) }}
                                    </div>
                                    <div class="text-[10px] text-slate-400 font-medium">
                                        {{ $card['floating_cheques_count'] > 0 ? $card['floating_cheques_count'].' uncleared' : '0 floating' }}
                                    </div>
                                </td>

                                <!-- 5. Attention Column -->
                                <td class="py-3.5 px-4 text-center">
                                    <div class="inline-flex flex-col items-center gap-1">
                                        <span class="inline-flex items-center gap-1 text-[11px] font-extrabold px-2.5 py-0.5 rounded-lg border {{ $statusBadgeClass }}">
                                            {{ $card['status'] }}
                                        </span>
                                        @if($card['pending_operation_count'] > 0)
                                            <span class="text-[10px] font-bold text-amber-700 font-mono">
                                                {{ $card['pending_operation_count'] }} pending
                                            </span>
                                        @endif
                                    </div>
                                </td>

                                <!-- 6. Outstanding Column -->
                                <td class="py-3.5 px-4 text-right">
                                    <div class="font-mono font-black text-sm text-slate-900">
                                        ₹{{ number_format($card['current_outstanding'], 2) }}
                                    </div>
                                    <div class="text-[10px] text-slate-400 font-medium">Settlement delta</div>
                                </td>

                                <!-- 7. Action Column -->
                                <td class="py-3.5 pl-4 pr-6 text-right">
                                    <a href="{{ $card['open_shop_url'] }}"
                                       class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-slate-900 hover:bg-emerald-700 text-white text-xs font-extrabold transition-all shadow-xs flex-shrink-0 cursor-pointer"
                                       title="Open {{ $card['shop_name'] }} Cashbook">
                                        <span>Open Shop</span>
                                        <i data-lucide="arrow-up-right" class="w-3.5 h-3.5"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-slate-50 font-black border-t-2 border-slate-200">
                        <tr>
                            <td class="py-4 pl-6 pr-4">
                                <div class="text-xs font-black uppercase tracking-wider text-slate-900">
                                    Total ({{ count($shopCards) }} {{ Str::plural('Shop', count($shopCards)) }})
                                </div>
                                <div class="text-[11px] text-slate-500 font-mono font-medium">
                                    Sum: ₹{{ number_format(array_sum(array_column($shopCards, 'total_collection')), 2) }}
                                </div>
                            </td>
                            <td class="py-4 px-4 text-right font-mono text-sm font-black text-emerald-700">
                                ₹{{ number_format(array_sum(array_column($shopCards, 'company_received')), 2) }}
                            </td>
                            <td class="py-4 px-4 text-right font-mono text-sm font-black text-sky-700">
                                ₹{{ number_format(array_sum(array_column($shopCards, 'cash_with_shop')), 2) }}
                            </td>
                            <td class="py-4 px-4 text-right font-mono text-sm font-black text-purple-700">
                                ₹{{ number_format(array_sum(array_column($shopCards, 'floating_cheques')), 2) }}
                            </td>
                            <td class="py-4 px-4 text-center">
                                @php
                                    $totPending = array_sum(array_column($shopCards, 'pending_operation_count'));
                                @endphp
                                @if($totPending > 0)
                                    <span class="inline-flex items-center gap-1 text-[11px] font-black px-2.5 py-1 rounded-lg bg-amber-100 text-amber-900 border border-amber-300">
                                        <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                                        {{ $totPending }} Pending
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-[11px] font-extrabold px-2.5 py-1 rounded-lg bg-emerald-100 text-emerald-900 border border-emerald-300">
                                        <i data-lucide="check" class="w-3 h-3"></i> Clear
                                    </span>
                                @endif
                            </td>
                            <td class="py-4 px-4 text-right font-mono text-sm font-black text-slate-900">
                                ₹{{ number_format(array_sum(array_column($shopCards, 'current_outstanding')), 2) }}
                            </td>
                            <td class="py-4 pl-4 pr-6 text-right"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>

    <!-- Daily Collections List -->
    <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                <i data-lucide="layers" class="w-4 h-4 text-emerald-600"></i>
                <span>Daily Money Flow Collections</span>
                <span class="text-xs text-slate-400 font-mono font-normal">({{ $items->total() }} records)</span>
            </h2>
        </div>

        @if($items->isEmpty())
            <div class="p-8 text-center bg-slate-50 rounded-2xl border border-dashed border-slate-200">
                <p class="text-xs text-slate-400 font-bold">No collections recorded for this filter on {{ \Carbon\Carbon::parse($businessDate)->format('d M Y') }}.</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach($items as $item)
                    <div class="p-4 rounded-2xl border border-slate-100 bg-slate-50/70 hover:bg-white hover:border-slate-200 hover:shadow-xs transition-all flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                        
                        <!-- Left Block: Method Icon, Shop & Destination -->
                        <div class="flex items-center gap-3.5 min-w-0">
                            <div class="w-10 h-10 rounded-2xl bg-white border border-slate-200 flex items-center justify-center text-slate-700 shadow-xs flex-shrink-0">
                                @if(str_contains(strtolower($item['payment_method']), 'paytm') || str_contains(strtolower($item['payment_method']), 'upi'))
                                    <i data-lucide="qr-code" class="w-5 h-5 text-sky-600"></i>
                                @elseif(str_contains(strtolower($item['payment_method']), 'cash'))
                                    <i data-lucide="banknote" class="w-5 h-5 text-emerald-600"></i>
                                @elseif(str_contains(strtolower($item['payment_method']), 'cheque'))
                                    <i data-lucide="file-check-2" class="w-5 h-5 text-purple-600"></i>
                                @elseif(str_contains(strtolower($item['payment_method']), 'card'))
                                    <i data-lucide="credit-card" class="w-5 h-5"></i>
                                @else
                                    <i data-lucide="smartphone" class="w-5 h-5"></i>
                                @endif
                            </div>

                            <div class="space-y-0.5">
                                <div class="flex items-center gap-2">
                                    <span class="font-extrabold text-sm text-slate-900">{{ $item['shop_name'] }}</span>
                                    <span class="px-2 py-0.5 rounded-md bg-slate-100 text-[10px] font-extrabold uppercase tracking-wider text-slate-600 border border-slate-200">
                                        {{ $item['payment_method'] }}
                                    </span>
                                </div>

                                <div class="text-xs text-slate-500 font-medium">
                                    @if($item['destination_name'])
                                        <span class="font-bold text-slate-700">{{ $item['destination_name'] }}</span>
                                    @elseif($item['location_name'])
                                        <span class="font-bold text-slate-700">{{ $item['location_name'] }}</span>
                                    @else
                                        <span>Company Bank Account</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Right Block: Amount, Status & Action -->
                        <div class="flex items-center justify-between sm:justify-end gap-4 w-full sm:w-auto border-t sm:border-t-0 pt-3 sm:pt-0 border-slate-100">
                            
                            <div class="text-left sm:text-right">
                                <div class="text-base sm:text-lg font-black text-slate-900 font-mono">
                                    ₹{{ number_format($item['amount'], 2) }}
                                </div>

                                <div>
                                    @if($item['display_status'] === 'RECEIVED')
                                        <span class="inline-flex items-center gap-1 text-[11px] font-extrabold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200">
                                            <i data-lucide="check" class="w-3 h-3"></i> RECEIVED
                                        </span>
                                    @elseif($item['display_status'] === 'NEEDS VERIFICATION')
                                        <span class="inline-flex items-center gap-1 text-[11px] font-extrabold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-md border border-amber-200">
                                            <i data-lucide="alert-circle" class="w-3 h-3"></i> NEEDS VERIFICATION
                                        </span>
                                    @elseif($item['display_status'] === 'CASH WITH SHOP')
                                        <span class="inline-flex items-center gap-1 text-[11px] font-extrabold text-sky-700 bg-sky-50 px-2 py-0.5 rounded-md border border-sky-200">
                                            <i data-lucide="store" class="w-3 h-3"></i> CASH WITH SHOP
                                        </span>
                                    @elseif($item['display_status'] === 'FLOATING')
                                        <span class="inline-flex items-center gap-1 text-[11px] font-extrabold text-purple-700 bg-purple-50 px-2 py-0.5 rounded-md border border-purple-200">
                                            <i data-lucide="clock" class="w-3 h-3"></i> FLOATING
                                        </span>
                                    @elseif($item['display_status'] === 'REJECTED')
                                        <span class="inline-flex items-center gap-1 text-[11px] font-extrabold text-rose-700 bg-rose-50 px-2 py-0.5 rounded-md border border-rose-200">
                                            <i data-lucide="x" class="w-3 h-3"></i> REJECTED
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-[11px] font-extrabold text-slate-700 bg-slate-100 px-2 py-0.5 rounded-md border border-slate-200">
                                            {{ $item['display_status'] }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <!-- Single Allowed Action: VIEW -->
                            <a href="{{ $item['detail_url'] }}"
                               class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-900 text-slate-700 hover:text-white text-xs font-extrabold transition-all shadow-xs flex-shrink-0 cursor-pointer">
                                <span>View</span>
                                <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                            </a>

                        </div>

                    </div>
                @endforeach
            </div>

            <!-- Transaction Paginator Links -->
            @if($items->hasPages())
                <div class="pt-4 border-t border-slate-100">
                    {{ $items->links() }}
                </div>
            @endif
        @endif
    </div>

    <!-- ── MONTHLY PENDING CALENDAR (Mobile-Optimized) ────────────────────── -->
    <div id="monthly-pending-calendar" class="bg-white p-5 sm:p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4">
        
        <!-- Calendar Header: Navigation & Month Label -->
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <span class="p-1.5 rounded-xl bg-slate-100 text-slate-700">
                        <i data-lucide="calendar-days" class="w-4 h-4"></i>
                    </span>
                    <h3 class="text-base sm:text-lg font-black text-slate-900">{{ $calendarData['month_title'] }}</h3>
                </div>
                <p class="text-[11px] font-bold text-slate-400 mt-0.5">
                    Click any date to switch day view; badges show pending items.
                </p>
            </div>

            <!-- Month Switcher (‹ Month ›) -->
            <div class="flex items-center gap-1.5">
                <a href="{{ route($currentRouteName, array_merge($routeParams, ['date' => $businessDate, 'calendar_month' => $calendarData['prev_month'], 'shop_id' => $selectedShopId, 'status' => $selectedStatus])) }}"
                   class="inline-flex items-center justify-center p-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 shadow-xs transition"
                   title="Previous Month"
                   aria-label="Previous Month">
                    <i data-lucide="chevron-left" class="w-4 h-4"></i>
                </a>

                <a href="{{ route($currentRouteName, array_merge($routeParams, ['date' => today()->toDateString(), 'calendar_month' => today()->format('Y-m'), 'shop_id' => $selectedShopId])) }}"
                   class="px-2.5 py-1.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-xs font-black text-slate-700 shadow-xs transition">
                    Current
                </a>

                <a href="{{ route($currentRouteName, array_merge($routeParams, ['date' => $businessDate, 'calendar_month' => $calendarData['next_month'], 'shop_id' => $selectedShopId, 'status' => $selectedStatus])) }}"
                   class="inline-flex items-center justify-center p-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 shadow-xs transition"
                   title="Next Month"
                   aria-label="Next Month">
                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                </a>
            </div>
        </div>

        <!-- 7-Column Day Names (M T W T F S S) -->
        <div class="grid grid-cols-7 gap-1 sm:gap-1.5 text-center text-[10px] sm:text-xs font-black uppercase text-slate-400 border-b border-slate-100 pb-2">
            <div>M</div>
            <div>T</div>
            <div>W</div>
            <div>T</div>
            <div>F</div>
            <div>S</div>
            <div>S</div>
        </div>

        <!-- Calendar Month Grid -->
        <div class="grid grid-cols-7 gap-1 sm:gap-1.5">
            @foreach($calendarData['weeks'] as $week)
                @foreach($week as $day)
                    @if($day === null)
                        <div class="h-11 sm:h-12 rounded-2xl bg-slate-50/40"></div>
                    @else
                        @php
                            $dateUrl = route($currentRouteName, array_merge($routeParams, [
                                'date' => $day['date'],
                                'calendar_month' => $calendarData['calendar_month'],
                                'shop_id' => $selectedShopId,
                            ]));
                            $pendingUrl = route($currentRouteName, array_merge($routeParams, [
                                'date' => $day['date'],
                                'status' => 'pending',
                                'calendar_month' => $calendarData['calendar_month'],
                                'shop_id' => $selectedShopId,
                            ]));
                        @endphp
                        <div class="h-11 sm:h-12 p-1 rounded-2xl flex flex-col items-center justify-between transition-all relative border {{ $day['is_selected'] ? 'bg-slate-900 border-slate-900 text-white shadow-xs' : ($day['is_today'] ? 'bg-emerald-50/60 border-emerald-300 text-emerald-950 ring-2 ring-emerald-500/20' : 'bg-slate-50/80 border-slate-100 hover:border-slate-300 text-slate-700') }}">
                            
                            <!-- Date Link -->
                            <a href="{{ $dateUrl }}" class="font-mono text-xs sm:text-sm font-black w-full text-center flex-1 flex items-center justify-center cursor-pointer">
                                {{ $day['day'] }}
                            </a>

                            <!-- Pending Badge (Only if > 0) -->
                            @if($day['pending_count'] > 0)
                                <a href="{{ $pendingUrl }}"
                                   class="px-1.5 py-0.2 rounded-full bg-amber-500 hover:bg-amber-600 text-white text-[9px] font-black leading-none shadow-xs transition cursor-pointer"
                                   title="{{ $day['pending_count'] }} pending on {{ $day['date'] }}">
                                    {{ $day['pending_count'] }}
                                </a>
                            @endif
                        </div>
                    @endif
                @endforeach
            @endforeach
        </div>

    </div>

</div>
@endsection
