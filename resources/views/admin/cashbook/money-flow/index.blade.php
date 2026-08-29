@extends('admin.cashbook.layouts.app')

@section('title', 'Money Flow — Cashbook')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 pb-12">

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-3xl border border-slate-200 shadow-sm">
        <div>
            <div class="flex items-center gap-2">
                <span class="p-2 rounded-2xl bg-emerald-50 text-emerald-700 border border-emerald-200">
                    <i data-lucide="activity" class="w-5 h-5"></i>
                </span>
                <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">Money Flow</h1>
            </div>
            <p class="text-xs text-slate-500 font-medium mt-1">
                Unified real-time company money position &amp; retail collection flows for 
                <span class="font-bold text-slate-800 font-mono">{{ \Carbon\Carbon::parse($businessDate)->format('d M Y') }}</span>
            </p>
        </div>

        <!-- Date & Filter Controls -->
        <form method="GET" action="{{ route('admin.cashbook.money-flow') }}" id="money-flow-filter-form" class="flex items-center gap-2 flex-wrap">
            <input type="hidden" name="status" value="{{ $selectedStatus }}">
            <input type="hidden" name="shop_id" value="{{ $selectedShopId }}">

            <!-- Quick Day Buttons -->
            <div class="inline-flex rounded-xl bg-slate-100 p-1 border border-slate-200 text-xs font-bold">
                <a href="{{ route('admin.cashbook.money-flow', ['date' => today()->toDateString(), 'shop_id' => $selectedShopId, 'status' => $selectedStatus]) }}"
                   class="px-3 py-1.5 rounded-lg transition {{ $businessDate === today()->toDateString() ? 'bg-white text-emerald-800 shadow-xs font-extrabold' : 'text-slate-600 hover:text-slate-900' }}">
                    Today
                </a>
                <a href="{{ route('admin.cashbook.money-flow', ['date' => today()->subDay()->toDateString(), 'shop_id' => $selectedShopId, 'status' => $selectedStatus]) }}"
                   class="px-3 py-1.5 rounded-lg transition {{ $businessDate === today()->subDay()->toDateString() ? 'bg-white text-emerald-800 shadow-xs font-extrabold' : 'text-slate-600 hover:text-slate-900' }}">
                    Yesterday
                </a>
            </div>

            <div class="flex items-center gap-1.5 bg-slate-100 px-3 py-1.5 rounded-xl border border-slate-200">
                <i data-lucide="calendar" class="w-4 h-4 text-slate-500"></i>
                <input type="date" name="date" value="{{ $businessDate }}" onchange="document.getElementById('money-flow-filter-form').submit()"
                       class="bg-transparent text-xs font-mono font-bold text-slate-800 border-none focus:outline-none cursor-pointer">
            </div>
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
                    {{ $summary['shops_holding_cash_count'] ?? 0 }} shops holding cash
                </div>
            </div>
        </div>

        <!-- 4. FLOATING CHEQUES -->
        <div class="bg-white p-5 rounded-3xl border border-purple-100 shadow-sm relative overflow-hidden flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-purple-700">Floating Cheques</span>
                <span class="p-2 rounded-xl bg-purple-50 text-purple-600">
                    <i data-lucide="file-check-2" class="w-4 h-4"></i>
                </span>
            </div>
            <div class="mt-3">
                <div class="text-2xl sm:text-3xl font-black text-slate-900 font-mono tracking-tight">
                    ₹{{ number_format($summary['floating_cheques']['total_floating'] ?? 0, 2) }}
                </div>
                <div class="flex items-center gap-1.5 text-[11px] font-bold text-purple-700 mt-1">
                    <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                    {{ $summary['floating_cheques_count'] ?? 0 }} uncleared cheques
                </div>
            </div>
        </div>

    </div>

    <!-- Filters & Search Bar -->
    <div class="bg-white p-4 rounded-3xl border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        
        <!-- Status Pill Tabs -->
        <div class="flex items-center gap-1.5 flex-wrap text-xs font-bold">
            <a href="{{ route('admin.cashbook.money-flow', ['date' => $businessDate, 'shop_id' => $selectedShopId, 'status' => 'all']) }}"
               class="px-3 py-1.5 rounded-xl transition {{ $selectedStatus === 'all' || empty($selectedStatus) ? 'bg-slate-900 text-white font-extrabold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                All Items ({{ count($items) }})
            </a>
            <a href="{{ route('admin.cashbook.money-flow', ['date' => $businessDate, 'shop_id' => $selectedShopId, 'status' => 'needs_attention']) }}"
               class="px-3 py-1.5 rounded-xl transition {{ $selectedStatus === 'needs_attention' ? 'bg-amber-600 text-white font-extrabold' : 'bg-amber-50 text-amber-800 hover:bg-amber-100 border border-amber-200' }}">
                Needs Attention
            </a>
            <a href="{{ route('admin.cashbook.money-flow', ['date' => $businessDate, 'shop_id' => $selectedShopId, 'status' => 'verified']) }}"
               class="px-3 py-1.5 rounded-xl transition {{ $selectedStatus === 'verified' ? 'bg-emerald-700 text-white font-extrabold' : 'bg-emerald-50 text-emerald-800 hover:bg-emerald-100 border border-emerald-200' }}">
                Verified Received
            </a>
            <a href="{{ route('admin.cashbook.money-flow', ['date' => $businessDate, 'shop_id' => $selectedShopId, 'status' => 'cash_with_shop']) }}"
               class="px-3 py-1.5 rounded-xl transition {{ $selectedStatus === 'cash_with_shop' ? 'bg-sky-700 text-white font-extrabold' : 'bg-sky-50 text-sky-800 hover:bg-sky-100 border border-sky-200' }}">
                Cash With Shops
            </a>
            <a href="{{ route('admin.cashbook.money-flow', ['date' => $businessDate, 'shop_id' => $selectedShopId, 'status' => 'floating']) }}"
               class="px-3 py-1.5 rounded-xl transition {{ $selectedStatus === 'floating' ? 'bg-purple-700 text-white font-extrabold' : 'bg-purple-50 text-purple-800 hover:bg-purple-100 border border-purple-200' }}">
                Floating Cheques
            </a>
        </div>

        <!-- Shop Dropdown Filter -->
        <div class="flex items-center gap-2">
            <span class="text-xs font-extrabold uppercase text-slate-400">Shop:</span>
            <select onchange="window.location.href='{{ route('admin.cashbook.money-flow', ['date' => $businessDate, 'status' => $selectedStatus]) }}&shop_id=' + this.value"
                    class="bg-slate-50 text-xs font-bold text-slate-800 px-3 py-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-emerald-600 cursor-pointer">
                <option value="">All Shops</option>
                @foreach($shops as $shop)
                    <option value="{{ $shop->shop_id }}" {{ $selectedShopId == $shop->shop_id ? 'selected' : '' }}>
                        {{ $shop->name }} ({{ $shop->code }})
                    </option>
                @endforeach
            </select>
        </div>

    </div>

    <!-- ALL MONEY FLOW LIST / CARDS -->
    <div class="space-y-3">
        <div class="flex items-center justify-between px-2">
            <h2 class="text-sm font-extrabold text-slate-900 tracking-tight uppercase">All Money Flow</h2>
            <span class="text-xs font-medium text-slate-500 font-mono">{{ count($items) }} entries</span>
        </div>

        @if(empty($items))
            <div class="bg-white rounded-3xl p-12 text-center border border-slate-200 shadow-sm space-y-3">
                <div class="h-12 w-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto">
                    <i data-lucide="inbox" class="w-6 h-6"></i>
                </div>
                <p class="text-sm font-bold text-slate-800">No money flow records found for this date and filter.</p>
                <p class="text-xs text-slate-500">Try selecting a different date or clearing the status filter.</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach($items as $item)
                    <div class="bg-white p-4 sm:p-5 rounded-3xl border border-slate-200 shadow-sm hover:border-emerald-300 transition-all flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        
                        <!-- Left Block: Shop & Method -->
                        <div class="flex items-start gap-3">
                            <div class="p-2.5 rounded-2xl {{ $item['payment_method'] === 'Cash' ? 'bg-sky-50 text-sky-700 border border-sky-200' : ($item['payment_method'] === 'Cheque' ? 'bg-purple-50 text-purple-700 border border-purple-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200') }} flex-shrink-0">
                                @if($item['payment_method'] === 'Cash')
                                    <i data-lucide="banknote" class="w-5 h-5"></i>
                                @elseif($item['payment_method'] === 'Cheque')
                                    <i data-lucide="file-check-2" class="w-5 h-5"></i>
                                @elseif($item['payment_method'] === 'Card')
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
        @endif
    </div>

</div>
@endsection
