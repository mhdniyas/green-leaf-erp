@extends('shop-owner.layouts.app')

@section('title', 'Vendor Purchases — ' . $shop->name)
@section('page_title', 'Vendor Purchases')
@section('page_description', 'Manage store vendor item purchases, track cash and credit payables, and synchronize cashbook entries.')

@section('content')
<div class="mx-auto max-w-7xl space-y-5 pb-16">
    {{-- Top Header & Quick Actions --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-200/80 pb-4">
        <div class="space-y-1">
            <div class="flex items-center gap-2">
                <a href="{{ route('shop-owner.cashbook.show') }}" class="inline-flex items-center gap-1 text-xs font-bold text-slate-500 hover:text-emerald-700 transition">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                    <span>Cashbook</span>
                </a>
                <span class="text-slate-300">&middot;</span>
                <span class="px-2 py-0.5 rounded-md bg-emerald-50 border border-emerald-200 text-[10px] font-black tracking-wider uppercase text-emerald-800">
                    {{ $shop->name }}
                </span>
            </div>
            <h1 class="text-xl sm:text-2xl font-black tracking-tight text-slate-950 uppercase flex items-center gap-2">
                <i data-lucide="shopping-bag" class="h-6 w-6 text-emerald-600"></i>
                <span>Vendor Purchases</span>
            </h1>
        </div>

        <div class="flex items-center gap-2">
            <button type="button" onclick="openNewVendorPurchaseModal()"
                    class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-black text-white shadow-sm hover:bg-emerald-700 active:scale-98 transition cursor-pointer">
                <i data-lucide="plus" class="h-4 w-4"></i>
                <span>+ New Vendor Purchase</span>
            </button>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/90 shadow-xs space-y-4">
        @php
            $currentPeriod = $filters['period'] ?? 'today';
            $baseParams = [
                'supplier_id' => $filters['supplier_id'],
                'category_id' => $filters['category_id'],
                'payment_method' => $filters['payment_method'],
                'search' => $filters['search'],
            ];
            $todayStr = now('Asia/Kolkata')->toDateString();
            $yesterdayStr = now('Asia/Kolkata')->subDay()->toDateString();
            $monthStart = now('Asia/Kolkata')->startOfMonth()->toDateString();
            $monthEnd = now('Asia/Kolkata')->endOfMonth()->toDateString();
        @endphp

        <form method="GET" action="{{ route('shop-owner.cashbook.vendor-purchases') }}" id="vp-filter-form" class="space-y-3">
            {{-- Quick Period Tabs --}}
            <div class="flex items-center justify-between flex-wrap gap-2 border-b border-slate-100 pb-3">
                <div class="flex items-center gap-1.5 overflow-x-auto pb-1 max-w-full">
                    <a href="{{ route('shop-owner.cashbook.vendor-purchases', array_merge($baseParams, ['period' => 'today'])) }}"
                       class="px-3 py-1.5 rounded-xl text-xs font-black transition {{ $currentPeriod === 'today' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        Today
                    </a>
                    <a href="{{ route('shop-owner.cashbook.vendor-purchases', array_merge($baseParams, ['period' => 'yesterday'])) }}"
                       class="px-3 py-1.5 rounded-xl text-xs font-black transition {{ $currentPeriod === 'yesterday' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        Yesterday
                    </a>
                    <a href="{{ route('shop-owner.cashbook.vendor-purchases', array_merge($baseParams, ['period' => 'month'])) }}"
                       class="px-3 py-1.5 rounded-xl text-xs font-black transition {{ $currentPeriod === 'month' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        This Month
                    </a>
                    <a href="{{ route('shop-owner.cashbook.vendor-purchases', array_merge($baseParams, ['period' => 'custom', 'start_date' => $filters['start_date'] ?: $todayStr, 'end_date' => $filters['end_date'] ?: $todayStr])) }}"
                       class="px-3 py-1.5 rounded-xl text-xs font-black transition {{ $currentPeriod === 'custom' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        Custom Period
                    </a>
                    <a href="{{ route('shop-owner.cashbook.vendor-purchases', array_merge($baseParams, ['period' => 'all'])) }}"
                       class="px-3 py-1.5 rounded-xl text-xs font-black transition {{ $currentPeriod === 'all' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        All
                    </a>
                </div>

                @if($filters['start_date'] && $filters['end_date'])
                    <div class="text-xs font-mono text-slate-500 font-bold">
                        {{ \Carbon\Carbon::parse($filters['start_date'])->format('d M Y') }} &mdash; {{ \Carbon\Carbon::parse($filters['end_date'])->format('d M Y') }}
                    </div>
                @endif
            </div>

            <input type="hidden" name="period" value="{{ $currentPeriod }}">

            {{-- Filter Fields Grid --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-2.5 pt-1">
                @if($currentPeriod === 'custom')
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">From Date</label>
                        <input type="date" name="start_date" value="{{ $filters['start_date'] }}"
                               class="w-full text-xs font-bold bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-2 text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">To Date</label>
                        <input type="date" name="end_date" value="{{ $filters['end_date'] }}"
                               class="w-full text-xs font-bold bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-2 text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                @endif

                {{-- Vendor Filter --}}
                <div class="{{ $currentPeriod === 'custom' ? '' : 'col-span-1 sm:col-span-2' }}">
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Vendor</label>
                    <select name="supplier_id" onchange="this.form.submit()"
                            class="w-full text-xs font-bold bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-2 text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 cursor-pointer">
                        <option value="">All Vendors</option>
                        @foreach($suppliers as $sup)
                            <option value="{{ $sup->id }}" @selected((int) ($filters['supplier_id'] ?? 0) === (int) $sup->id)>
                                {{ $sup->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Category Filter --}}
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Category</label>
                    <select name="category_id" onchange="this.form.submit()"
                            class="w-full text-xs font-bold bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-2 text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 cursor-pointer">
                        <option value="">All Categories</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" @selected((int) ($filters['category_id'] ?? 0) === (int) $cat->id)>
                                {{ $cat->displayName() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Payment Filter --}}
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Payment</label>
                    <select name="payment_method" onchange="this.form.submit()"
                            class="w-full text-xs font-bold bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-2 text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 cursor-pointer">
                        <option value="all" @selected(($filters['payment_method'] ?? 'all') === 'all')>All Payments</option>
                        <option value="Cash" @selected(strcasecmp((string) ($filters['payment_method'] ?? ''), 'Cash') === 0)>Cash Only</option>
                        <option value="Credit" @selected(strcasecmp((string) ($filters['payment_method'] ?? ''), 'Credit') === 0)>Credit Only</option>
                    </select>
                </div>

                {{-- Search Box --}}
                <div class="{{ $currentPeriod === 'custom' ? 'col-span-1' : 'col-span-1 sm:col-span-2' }}">
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Search</label>
                    <div class="relative">
                        <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Bill #, item, vendor..."
                               class="w-full text-xs font-bold bg-slate-50 border border-slate-200 rounded-xl pl-8 pr-2.5 py-2 text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5 pointer-events-none"></i>
                    </div>
                </div>
            </div>

            @if($currentPeriod === 'custom')
                <div class="flex justify-end pt-1">
                    <button type="submit" class="px-4 py-1.5 bg-emerald-700 text-white rounded-xl text-xs font-bold hover:bg-emerald-800 transition">
                        Apply Filters
                    </button>
                </div>
            @endif
        </form>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block mb-1">Total Purchases</span>
            <div class="flex items-baseline justify-between">
                <span class="font-mono text-xl font-black text-slate-900">₹{{ number_format($summary['total_amount'], 2) }}</span>
                <span class="text-xs font-extrabold text-slate-500">{{ $summary['total_invoices'] }} bills</span>
            </div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-emerald-200 bg-emerald-50/30 shadow-xs">
            <span class="text-[10px] font-black uppercase tracking-wider text-emerald-800 block mb-1">Cash Purchases</span>
            <div class="flex items-baseline justify-between">
                <span class="font-mono text-xl font-black text-emerald-700">₹{{ number_format($summary['cash_amount'], 2) }}</span>
                <span class="text-[10px] font-bold text-emerald-600">Shop Cash</span>
            </div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-amber-200 bg-amber-50/30 shadow-xs">
            <span class="text-[10px] font-black uppercase tracking-wider text-amber-800 block mb-1">Credit Purchases</span>
            <div class="flex items-baseline justify-between">
                <span class="font-mono text-xl font-black text-amber-700">₹{{ number_format($summary['credit_amount'], 2) }}</span>
                <span class="text-[10px] font-bold text-amber-600">Vendor Credit</span>
            </div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-rose-200 bg-rose-50/30 shadow-xs">
            <span class="text-[10px] font-black uppercase tracking-wider text-rose-800 block mb-1">Outstanding Liability</span>
            <div class="flex items-baseline justify-between">
                <span class="font-mono text-xl font-black text-rose-700">₹{{ number_format($summary['credit_outstanding'], 2) }}</span>
                <span class="text-[10px] font-bold text-rose-600">Unpaid Credit</span>
            </div>
        </div>
    </div>

    {{-- Purchases Table --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 flex items-center gap-1.5">
                <i data-lucide="list" class="h-4 w-4 text-emerald-600"></i>
                <span>Purchases List</span>
            </h3>
            <span class="text-xs text-slate-500 font-bold">Showing {{ $invoices->count() }} of {{ $invoices->total() }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200/80 text-[10px] font-black uppercase tracking-wider text-slate-500">
                        <th class="py-3 px-4">Date</th>
                        <th class="py-3 px-4">Vendor</th>
                        <th class="py-3 px-4">Category / Header</th>
                        <th class="py-3 px-4">Payment</th>
                        <th class="py-3 px-4">Items</th>
                        <th class="py-3 px-4 text-right">Total</th>
                        <th class="py-3 px-4 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs font-medium">
                    @forelse($invoices as $inv)
                        @php
                            $cart = $inv->purchaserCart;
                            $items = $cart?->items ?? collect();
                            $itemCount = $items->count();
                            $netTotal = (float) ($inv->amount - $inv->discount_amount);
                            $date = $cart?->business_date ?? $inv->original_business_date ?? $inv->created_at;
                            $dateStr = $date ? \Carbon\Carbon::parse($date)->toDateString() : '';
                            $isCash = strcasecmp((string) $inv->payment_method, 'Cash') === 0;
                            $setting = $inv->shopLedgerEntrySetting;
                            $isActionAllowed = ($isAdmin ?? false) || ($dateStr !== '' && $dateStr >= ($cutoffDate ?? now('Asia/Kolkata')->startOfDay()->subDays(3)->toDateString()));
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition group">
                            <td class="py-3 px-4 whitespace-nowrap">
                                <span class="font-bold text-slate-900 block">{{ $date ? \Carbon\Carbon::parse($date)->format('d M') : '-' }}</span>
                                <span class="font-mono text-[10px] text-slate-400 block">{{ $inv->invoice_number }}</span>
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-black text-slate-900">{{ $inv->supplier?->name ?? 'Vendor #'.$inv->supplier_id }}</div>
                                @if($inv->supplier?->mobile_number)
                                    <div class="text-[10px] text-slate-400 font-mono">{{ $inv->supplier->mobile_number }}</div>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-bold text-slate-800">{{ $setting?->displayName() ?? 'Vendor Purchase' }}</div>
                                <div class="text-[10px] text-slate-400 font-semibold">{{ $setting?->headerGroup?->name ?? 'Expenses' }}</div>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                @if($isCash)
                                    <span class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2 py-0.5 text-[10px] font-extrabold text-emerald-700 border border-emerald-200">
                                        <i data-lucide="banknote" class="h-3 w-3"></i>
                                        Cash
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-md bg-amber-50 px-2 py-0.5 text-[10px] font-extrabold text-amber-800 border border-amber-200">
                                        <i data-lucide="clock" class="h-3 w-3"></i>
                                        Credit
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <span class="font-bold text-slate-700">{{ $itemCount }} {{ Str::plural('item', $itemCount) }}</span>
                                @if($items->isNotEmpty())
                                    <div class="text-[10px] text-slate-400 truncate max-w-xs">
                                        {{ $items->pluck('product.name')->filter()->take(2)->join(', ') }}
                                        @if($itemCount > 2) +{{ $itemCount - 2 }} more @endif
                                    </div>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right whitespace-nowrap">
                                <span class="font-mono text-sm font-black text-slate-950">₹{{ number_format($netTotal, 2) }}</span>
                            </td>
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5">
                                    <button type="button" onclick="viewPurchaseDetails({{ $inv->id }})"
                                            class="inline-flex items-center gap-1 rounded-lg bg-slate-100 hover:bg-slate-200 px-2.5 py-1 text-[11px] font-bold text-slate-700 transition cursor-pointer">
                                        <i data-lucide="eye" class="h-3 w-3"></i>
                                        <span>View</span>
                                    </button>
                                    @if($isActionAllowed)
                                        <button type="button" onclick="openEditPurchaseModal({{ $inv->id }})"
                                                class="inline-flex items-center gap-1 rounded-lg bg-indigo-50 hover:bg-indigo-100 px-2.5 py-1 text-[11px] font-bold text-indigo-700 border border-indigo-200 transition cursor-pointer">
                                            <i data-lucide="edit-3" class="h-3 w-3"></i>
                                            <span>Edit</span>
                                        </button>
                                        <button type="button" onclick="confirmCancelPurchase({{ $inv->id }}, '{{ $inv->invoice_number }}')"
                                                class="inline-flex items-center gap-1 rounded-lg bg-rose-50 hover:bg-rose-100 px-2.5 py-1 text-[11px] font-bold text-rose-700 border border-rose-200 transition cursor-pointer">
                                            <i data-lucide="trash-2" class="h-3 w-3"></i>
                                            <span>Delete</span>
                                        </button>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-lg bg-slate-50 px-2 py-1 text-[10px] font-bold text-slate-400 border border-slate-200/60 cursor-not-allowed" title="Actions locked: Older than 3 days. Contact admin to edit.">
                                            <i data-lucide="lock" class="h-3 w-3"></i>
                                            <span>Locked</span>
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400">
                                <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 mx-auto flex items-center justify-center mb-2">
                                    <i data-lucide="shopping-bag" class="h-6 w-6"></i>
                                </div>
                                <p class="text-sm font-bold text-slate-600">No vendor purchases found</p>
                                <p class="text-xs text-slate-400 mt-0.5">Try adjusting your filters or record a new vendor purchase.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($invoices->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>
</div>

{{-- ── VIEW PURCHASE MODAL ────────────────────────────────────────── --}}
<div id="view-purchase-modal" onclick="handleModalBackdropClick(event, 'view-purchase-modal')"
     class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-slate-900/50 backdrop-blur-xs hidden transition-all">
    <div onclick="event.stopPropagation()"
         class="w-full max-w-2xl rounded-t-2xl sm:rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl flex flex-col max-h-[90vh]">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-xl bg-violet-600 flex items-center justify-center text-white shrink-0">
                    <i data-lucide="file-text" class="h-4 w-4"></i>
                </div>
                <div>
                    <h3 class="text-sm font-black uppercase text-slate-900" id="view-modal-title">PURCHASE DETAILS</h3>
                    <p class="text-[11px] font-bold text-slate-400" id="view-modal-subtitle"></p>
                </div>
            </div>
            <button type="button" onclick="closeModal('view-purchase-modal')" class="h-8 w-8 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 flex items-center justify-center cursor-pointer">
                <i data-lucide="x" class="h-4 w-4"></i>
            </button>
        </div>

        <div id="view-modal-body" class="flex-1 overflow-y-auto py-4 space-y-4">
            {{-- Loaded dynamically --}}
        </div>

        <div class="flex items-center justify-between border-t border-slate-100 pt-3">
            <div id="view-modal-footer-left"></div>
            <button type="button" onclick="closeModal('view-purchase-modal')" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 cursor-pointer">
                Close
            </button>
        </div>
    </div>
</div>

{{-- ── EDIT PURCHASE MODAL ────────────────────────────────────────── --}}
<div id="edit-purchase-modal" onclick="handleModalBackdropClick(event, 'edit-purchase-modal')"
     class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-slate-900/50 backdrop-blur-xs hidden transition-all">
    <div onclick="event.stopPropagation()"
         class="w-full max-w-xl rounded-t-2xl sm:rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl flex flex-col max-h-[90vh]">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-xl bg-indigo-600 flex items-center justify-center text-white shrink-0">
                    <i data-lucide="edit-3" class="h-4 w-4"></i>
                </div>
                <div>
                    <h3 class="text-sm font-black uppercase text-slate-900">EDIT VENDOR PURCHASE</h3>
                    <p class="text-[11px] font-bold text-slate-400" id="edit-modal-subtitle"></p>
                </div>
            </div>
            <button type="button" onclick="closeModal('edit-purchase-modal')" class="h-8 w-8 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 flex items-center justify-center cursor-pointer">
                <i data-lucide="x" class="h-4 w-4"></i>
            </button>
        </div>

        <form id="edit-purchase-form" onsubmit="event.preventDefault(); submitEditPurchase();" class="flex-1 overflow-y-auto py-3.5 space-y-3.5">
            <input type="hidden" id="edit-purchase-id" value="">

            <div id="edit-error-alert" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs font-bold text-rose-800 hidden"></div>

            {{-- Vendor Selection --}}
            <div class="space-y-1">
                <label class="block text-[11px] font-black uppercase tracking-wider text-slate-700">Vendor *</label>
                <select id="edit-vendor-select" name="supplier_id" required class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:bg-white focus:border-indigo-500 focus:outline-none">
                    @foreach($suppliers as $sup)
                        <option value="{{ $sup->id }}" data-credit="{{ $sup->pivot?->credit_approved ?? $sup->credit_approved ? '1' : '0' }}">
                            {{ $sup->name }} ({{ $sup->mobile_number ?: 'No phone' }})
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Payment Radio --}}
            <div class="space-y-1.5">
                <label class="block text-[11px] font-black uppercase tracking-wider text-slate-700">Payment Method *</label>
                <div class="grid grid-cols-2 gap-2.5">
                    <label class="flex items-center gap-2 rounded-xl border border-slate-200 p-2.5 cursor-pointer has-checked:border-emerald-500 has-checked:bg-emerald-50/50">
                        <input type="radio" name="edit_payment_method" value="Cash" id="edit-pay-cash" class="text-emerald-600">
                        <span class="text-xs font-black text-slate-900">Cash</span>
                    </label>
                    <label class="flex items-center gap-2 rounded-xl border border-slate-200 p-2.5 cursor-pointer has-checked:border-amber-500 has-checked:bg-amber-50/50">
                        <input type="radio" name="edit_payment_method" value="Credit" id="edit-pay-credit" class="text-amber-600">
                        <span class="text-xs font-black text-slate-900">Credit</span>
                    </label>
                </div>
            </div>

            {{-- Products Table --}}
            <div class="space-y-2 pt-1 border-t border-slate-100">
                <div class="flex items-center justify-between">
                    <label class="block text-[11px] font-black uppercase tracking-wider text-slate-700">Products *</label>
                    <button type="button" onclick="addEditProductRow()" class="inline-flex items-center gap-1 rounded-full bg-indigo-50 border border-indigo-200 px-2.5 py-1 text-[11px] font-extrabold text-indigo-700 hover:bg-indigo-100 cursor-pointer">
                        <i data-lucide="plus" class="h-3.5 w-3.5"></i>
                        <span>Add Item</span>
                    </button>
                </div>

                <div id="edit-products-list" class="space-y-2"></div>

                <div class="flex items-center justify-between rounded-xl bg-slate-50 p-3 border border-slate-200">
                    <span class="text-xs font-black uppercase text-slate-600">Total Purchase Value:</span>
                    <span id="edit-grand-total" class="font-mono text-base font-black text-slate-950">₹0.00</span>
                </div>
            </div>

            {{-- Bill & Note --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 pt-1 border-t border-slate-100">
                <div>
                    <label class="block text-[11px] font-black uppercase text-slate-700">Bill / Reference</label>
                    <input type="text" id="edit-bill-number" class="h-9 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:border-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-[11px] font-black uppercase text-slate-700">Note</label>
                    <input type="text" id="edit-notes" class="h-9 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:border-indigo-500 focus:outline-none">
                </div>
            </div>
        </form>

        <div class="flex items-center justify-end gap-2.5 border-t border-slate-100 pt-3">
            <button type="button" onclick="closeModal('edit-purchase-modal')" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 cursor-pointer">
                Cancel
            </button>
            <button type="button" onclick="submitEditPurchase()" class="rounded-xl bg-indigo-600 px-5 py-2 text-xs font-black text-white hover:bg-indigo-700 active:scale-98 cursor-pointer">
                Save Changes
            </button>
        </div>
    </div>
</div>

{{-- Include Create Vendor Purchase Modals --}}
@include('shop-owner.cashbook.partials.modals.vendor-purchase')
@include('shop-owner.cashbook.partials.modals.vendor-purchase-scripts')

<script>
    const availableProducts = @json($products);
    const availableCategories = @json($categories);
    const availableSuppliers = @json($suppliers);
    const updatePurchaseBaseUrl = @json(url('/shop-owner/cashbook/vendor-purchases'));

    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('open') === 'new') {
            const catId = urlParams.get('category_id');
            openVendorPurchaseModal(catId);
        }
    });

    function openNewVendorPurchaseModal() {
        if (typeof openVendorPurchaseModal === 'function') {
            openVendorPurchaseModal();
        } else {
            openModal('vendor-purchase-modal');
        }
    }

    async function viewPurchaseDetails(invoiceId) {
        try {
            const res = await fetch(`${updatePurchaseBaseUrl}/${invoiceId}`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Failed to load details.');

            const p = data.purchase;
            document.getElementById('view-modal-title').textContent = `PURCHASE #${p.invoice_number}`;
            document.getElementById('view-modal-subtitle').textContent = `${p.business_date} • ${p.category_name} (${p.header_name})`;

            let itemsHtml = p.items.map(item => `
                <tr class="border-b border-slate-100 text-xs">
                    <td class="py-2.5 px-3 font-bold text-slate-900">${escapeHtml(item.product_name)}</td>
                    <td class="py-2.5 px-3 font-semibold text-slate-700">${item.quantity} ${escapeHtml(item.unit)}</td>
                    <td class="py-2.5 px-3 font-mono font-bold text-slate-900 text-right">₹${item.line_total.toFixed(2)}</td>
                    <td class="py-2.5 px-3 font-mono text-[11px] text-slate-500 text-right">₹${item.avg_buy.toFixed(2)}/${escapeHtml(item.unit)}</td>
                </tr>
            `).join('');

            document.getElementById('view-modal-body').innerHTML = `
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 bg-slate-50 p-3 rounded-xl border border-slate-200">
                    <div>
                        <span class="text-[10px] font-black uppercase text-slate-400 block">Vendor</span>
                        <span class="text-xs font-bold text-slate-900 block truncate">${escapeHtml(p.supplier_name)}</span>
                        <span class="text-[10px] font-mono text-slate-500">${escapeHtml(p.supplier_mobile || '')}</span>
                    </div>
                    <div>
                        <span class="text-[10px] font-black uppercase text-slate-400 block">Payment</span>
                        <span class="inline-flex items-center gap-1 rounded px-2 py-0.5 text-[10px] font-extrabold ${p.payment_method.toLowerCase() === 'cash' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'}">
                            ${escapeHtml(p.payment_method)}
                        </span>
                    </div>
                    <div>
                        <span class="text-[10px] font-black uppercase text-slate-400 block">Category</span>
                        <span class="text-xs font-bold text-slate-800 block truncate">${escapeHtml(p.category_name)}</span>
                    </div>
                    <div>
                        <span class="text-[10px] font-black uppercase text-slate-400 block">Header</span>
                        <span class="text-xs font-bold text-slate-800 block truncate">${escapeHtml(p.header_name)}</span>
                    </div>
                </div>

                ${p.notes ? `
                    <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-2.5 text-xs text-slate-600 font-medium">
                        <span class="font-bold text-slate-800">Note:</span> ${escapeHtml(p.notes)}
                    </div>
                ` : ''}

                <div class="rounded-xl border border-slate-200 overflow-hidden">
                    <table class="w-full text-left">
                        <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-wider text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="py-2 px-3">Product</th>
                                <th class="py-2 px-3">Qty</th>
                                <th class="py-2 px-3 text-right">Total Price</th>
                                <th class="py-2 px-3 text-right">Avg Buy</th>
                            </tr>
                        </thead>
                        <tbody>${itemsHtml}</tbody>
                        <tfoot class="bg-slate-50/80 font-bold border-t border-slate-200 text-xs">
                            <tr>
                                <td colspan="2" class="py-2.5 px-3 uppercase text-slate-600">Total Purchase Value:</td>
                                <td class="py-2.5 px-3 font-mono font-black text-slate-950 text-right">₹${p.net_amount.toFixed(2)}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            `;

            document.getElementById('view-modal-footer-left').innerHTML = p.is_edit_allowed ? `
                <button type="button" onclick="closeModal('view-purchase-modal'); openEditPurchaseModal(${p.id});" class="inline-flex items-center gap-1 rounded-xl bg-indigo-50 border border-indigo-200 px-3 py-1.5 text-xs font-bold text-indigo-700 hover:bg-indigo-100 cursor-pointer">
                    <i data-lucide="edit-3" class="h-3.5 w-3.5"></i>
                    <span>Edit Purchase</span>
                </button>
            ` : '';

            openModal('view-purchase-modal');
            if (window.lucide) lucide.createIcons();
        } catch (err) {
            alert(err.message || 'Error loading purchase details.');
        }
    }

    async function openEditPurchaseModal(invoiceId) {
        try {
            const res = await fetch(`${updatePurchaseBaseUrl}/${invoiceId}`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Failed to load details.');

            const p = data.purchase;
            document.getElementById('edit-purchase-id').value = p.id;
            document.getElementById('edit-modal-subtitle').textContent = `Bill #${p.invoice_number} • ${p.business_date}`;
            document.getElementById('edit-bill-number').value = p.invoice_number || '';
            document.getElementById('edit-notes').value = p.notes || '';
            document.getElementById('edit-vendor-select').value = p.supplier_id || '';

            if (p.payment_method.toLowerCase() === 'credit') {
                document.getElementById('edit-pay-credit').checked = true;
            } else {
                document.getElementById('edit-pay-cash').checked = true;
            }

            const list = document.getElementById('edit-products-list');
            list.innerHTML = '';
            (p.items || []).forEach(item => {
                addEditProductRow(item.product_id, item.quantity, item.line_total);
            });

            if ((p.items || []).length === 0) {
                addEditProductRow();
            }

            recalculateEditTotals();
            openModal('edit-purchase-modal');
        } catch (err) {
            alert(err.message || 'Error loading edit form.');
        }
    }

    function addEditProductRow(productId = '', qty = '', totalPrice = '') {
        const list = document.getElementById('edit-products-list');
        const rowId = 'edit-row-' + Math.random().toString(36).substr(2, 9);

        let optionsHtml = '<option value="">-- Select Product --</option>';
        availableProducts.forEach(prod => {
            optionsHtml += `<option value="${prod.id}" ${String(prod.id) === String(productId) ? 'selected' : ''}>${escapeHtml(prod.name)} (${escapeHtml(prod.unit || 'kg')})</option>`;
        });

        const row = document.createElement('div');
        row.id = rowId;
        row.className = 'grid grid-cols-12 gap-2 items-center bg-slate-50/80 p-2 rounded-xl border border-slate-200/80 edit-product-row';
        row.innerHTML = `
            <div class="col-span-5">
                <select class="edit-prod-select h-9 w-full rounded-lg border border-slate-200 bg-white px-2 text-xs font-bold text-slate-800 focus:outline-none" onchange="recalculateEditTotals()">
                    ${optionsHtml}
                </select>
            </div>
            <div class="col-span-3">
                <input type="number" step="any" min="0.001" placeholder="Qty" value="${qty}" class="edit-prod-qty h-9 w-full rounded-lg border border-slate-200 bg-white px-2 text-xs font-bold text-slate-800 text-right focus:outline-none" oninput="recalculateEditTotals()">
            </div>
            <div class="col-span-3">
                <input type="number" step="any" min="0" placeholder="Total ₹" value="${totalPrice}" class="edit-prod-price h-9 w-full rounded-lg border border-slate-200 bg-white px-2 text-xs font-bold text-slate-800 text-right focus:outline-none" oninput="recalculateEditTotals()">
            </div>
            <div class="col-span-1 text-center">
                <button type="button" onclick="document.getElementById('${rowId}').remove(); recalculateEditTotals();" class="text-rose-500 hover:text-rose-700 cursor-pointer">
                    <i data-lucide="trash" class="h-4 w-4"></i>
                </button>
            </div>
        `;
        list.appendChild(row);
        if (window.lucide) lucide.createIcons();
    }

    function recalculateEditTotals() {
        let grandTotal = 0;
        document.querySelectorAll('#edit-products-list .edit-product-row').forEach(row => {
            const price = parseFloat(row.querySelector('.edit-prod-price')?.value) || 0;
            grandTotal += price;
        });
        document.getElementById('edit-grand-total').textContent = '₹' + grandTotal.toFixed(2);
    }

    async function submitEditPurchase() {
        const id = document.getElementById('edit-purchase-id').value;
        const errEl = document.getElementById('edit-error-alert');
        errEl.classList.add('hidden');

        const items = [];
        document.querySelectorAll('#edit-products-list .edit-product-row').forEach(row => {
            const prodId = parseInt(row.querySelector('.edit-prod-select')?.value, 10);
            const qty = parseFloat(row.querySelector('.edit-prod-qty')?.value);
            const totalPrice = parseFloat(row.querySelector('.edit-prod-price')?.value);
            if (prodId && qty > 0 && totalPrice >= 0) {
                items.push({
                    product_id: prodId,
                    quantity: qty,
                    total_price: totalPrice,
                    unit_price: qty > 0 ? (totalPrice / qty) : 0,
                });
            }
        });

        if (items.length === 0) {
            errEl.textContent = 'Please add at least one valid product item.';
            errEl.classList.remove('hidden');
            return;
        }

        const payload = {
            _method: 'PUT',
            supplier_id: parseInt(document.getElementById('edit-vendor-select').value, 10),
            payment_method: document.querySelector('input[name="edit_payment_method"]:checked')?.value || 'Cash',
            bill_number: document.getElementById('edit-bill-number').value.trim(),
            notes: document.getElementById('edit-notes').value.trim(),
            items: items,
        };

        try {
            const res = await fetch(`${updatePurchaseBaseUrl}/${id}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify(payload)
            });

            const data = await res.json();
            if (!data.success) {
                throw new Error(data.message || 'Failed to update purchase.');
            }

            window.location.reload();
        } catch (err) {
            errEl.textContent = err.message || 'Error updating purchase.';
            errEl.classList.remove('hidden');
        }
    }

    function confirmCancelPurchase(invoiceId, invoiceNumber) {
        if (!confirm(`Are you sure you want to delete/cancel Purchase #${invoiceNumber}? This will safely reverse related cashbook entries and vendor liabilities.`)) {
            return;
        }

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `${updatePurchaseBaseUrl}/${invoiceId}`;
        form.innerHTML = `
            <input type="hidden" name="_method" value="DELETE">
            <input type="hidden" name="_token" value="${document.querySelector('meta[name="csrf-token"]')?.content || ''}">
            <input type="hidden" name="reason" value="Deleted by shop owner">
        `;
        document.body.appendChild(form);
        form.submit();
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"']/g, m => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[m]));
    }
</script>
@endsection
