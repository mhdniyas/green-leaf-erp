@extends('admin.cashbook.layouts.app')

@section('title', 'Inventory — Bill Pending & Loadout Not Billed — Green Leaf Cashbook')

@section('header_title')
    <i data-lucide="boxes" class="w-5 h-5 text-emerald-600"></i> Cashbook Inventory
@endsection

@section('header_subtitle')
    Physical load receipts pending vendor bills &amp; shop loadouts not billed.
@endsection

@section('content')
    <div class="mx-auto max-w-5xl space-y-5">
        <!-- Top Title & Filter Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-1">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Cashbook Inventory</h1>
                <p class="text-xs font-bold text-slate-500 mt-0.5">Warehouse Receipts &amp; Shop Outflow Reconciliations</p>
            </div>

            <!-- Tab Switcher: Bill Pending vs Loadout Not Billed -->
            <div class="inline-flex rounded-2xl bg-slate-200/80 p-1 shadow-inner">
                <a href="{{ route('admin.cashbook.inventory', ['section' => 'bill_pending', 'search' => $search, 'date' => $date]) }}"
                   class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-black transition-all {{ $section === 'bill_pending' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                    <i data-lucide="clock" class="w-3.5 h-3.5 {{ $section === 'bill_pending' ? 'text-amber-600' : 'text-slate-400' }}"></i>
                    <span>Bill Pending</span>
                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-black text-amber-800">{{ $billPendingReceipts->total() }}</span>
                </a>
                <a href="{{ route('admin.cashbook.inventory', ['section' => 'loadout_not_billed', 'search' => $search, 'date' => $date]) }}"
                   class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-black transition-all {{ $section === 'loadout_not_billed' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">
                    <i data-lucide="truck" class="w-3.5 h-3.5 {{ $section === 'loadout_not_billed' ? 'text-blue-600' : 'text-slate-400' }}"></i>
                    <span>Loadout Not Billed</span>
                    <span class="rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-black text-blue-800">{{ $loadoutNotBilled->total() }}</span>
                </a>
            </div>
        </div>

        <!-- Search / Filter Form -->
        <form method="GET" action="{{ route('admin.cashbook.inventory') }}" class="flex flex-wrap items-center gap-2.5">
            <input type="hidden" name="section" value="{{ $section }}">
            <div class="relative flex-1 min-w-[200px]">
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2"></i>
                <input type="text"
                       name="search"
                       value="{{ $search }}"
                       placeholder="{{ $section === 'bill_pending' ? 'Search GRN #, Vendor, or Notes...' : 'Search Product or Shop...' }}"
                       class="w-full pl-9 pr-4 py-2 text-xs font-medium rounded-2xl bg-white border border-slate-200 shadow-xs focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
            </div>
            <input type="date"
                   name="date"
                   value="{{ $date }}"
                   class="px-3 py-2 text-xs font-medium rounded-2xl bg-white border border-slate-200 shadow-xs focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
            <button type="submit" class="px-4 py-2 text-xs font-black rounded-2xl bg-slate-900 text-white shadow-xs hover:bg-slate-800 transition-all">
                Filter
            </button>
            @if($search || $date)
                <a href="{{ route('admin.cashbook.inventory', ['section' => $section]) }}" class="px-3 py-2 text-xs font-bold rounded-2xl bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all">
                    Reset
                </a>
            @endif
        </form>

        @if($section === 'bill_pending')
            <!-- SECTION A: BILL PENDING RECEIPTS -->
            <div class="space-y-3">
                <div class="flex items-center justify-between px-1">
                    <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider">A. Goods Received — Bill Pending</h2>
                    <span class="text-xs font-bold text-slate-500">Physical stock added to warehouse; financial bill not yet linked</span>
                </div>

                @if($billPendingReceipts->isEmpty())
                    <div class="p-8 text-center bg-white rounded-3xl border border-slate-200/90 shadow-xs">
                        <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-2">
                            <i data-lucide="check-circle-2" class="w-6 h-6"></i>
                        </div>
                        <h3 class="text-sm font-black text-slate-900">All Physical Receipts Billed</h3>
                        <p class="text-xs text-slate-500 mt-1">There are no pending warehouse goods receipts without a vendor bill.</p>
                    </div>
                @else
                    <div class="grid grid-cols-1 gap-3">
                        @foreach($billPendingReceipts as $grn)
                            <div class="p-4 bg-white rounded-3xl border border-slate-200/90 shadow-xs hover:shadow-md transition-all">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-slate-100 pb-3">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-9 h-9 rounded-2xl bg-amber-50 text-amber-700 flex items-center justify-center font-black text-xs">
                                            GRN
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <h3 class="text-sm font-black text-slate-900">{{ $grn->purchaseOrder?->supplier?->name ?? 'Vendor Unassigned' }}</h3>
                                                <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-black text-amber-800">
                                                    BILL PENDING
                                                </span>
                                            </div>
                                            <p class="text-[11px] font-bold text-slate-500">
                                                GRN: <span class="font-mono text-slate-700">{{ $grn->grn_number }}</span>
                                                @if($grn->purchaseOrder)
                                                    • PO: <span class="font-mono text-slate-700">{{ $grn->purchaseOrder->po_number }}</span>
                                                @endif
                                                • Date: {{ $grn->received_at?->format('d M Y') ?? 'N/A' }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="text-left sm:text-right text-[11px] font-bold text-slate-500">
                                        <div>Dest: <span class="text-slate-800">{{ $grn->purchaseOrder?->destinationShop?->name ?? 'Central Warehouse' }}</span></div>
                                        <div>Receiver: <span class="text-slate-800">{{ $grn->receivedBy?->name ?? 'Receiver' }}</span></div>
                                        @if($grn->updatedBy)
                                            <div>Updated By: <span class="text-slate-800">{{ $grn->updatedBy->name }}</span></div>
                                        @endif
                                    </div>
                                </div>

                                <!-- Items List -->
                                <div class="mt-3">
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($grn->items as $item)
                                            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-slate-50 border border-slate-200/80 text-xs font-bold text-slate-800">
                                                <span>{{ $item->product?->name ?? 'Item #'.$item->product_id }}</span>
                                                <span class="text-emerald-700 font-black">{{ (float) $item->received_qty }} {{ $item->product?->unit ?? 'KG' }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                @if($grn->notes)
                                    <div class="mt-2.5 text-xs text-slate-600 bg-slate-50/70 p-2 rounded-xl border border-slate-100">
                                        <span class="font-bold text-slate-700">Note:</span> {{ $grn->notes }}
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
        @else
            <!-- SECTION B: LOADOUT NOT BILLED -->
            <div class="space-y-3">
                <div class="flex items-center justify-between px-1">
                    <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider">B. Loadout Not Billed</h2>
                    <span class="text-xs font-bold text-slate-500">Dispatched shop items without purchaser / invoice coverage</span>
                </div>

                @if($loadoutNotBilled->isEmpty())
                    <div class="p-8 text-center bg-white rounded-3xl border border-slate-200/90 shadow-xs">
                        <div class="w-12 h-12 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center mx-auto mb-2">
                            <i data-lucide="check-circle-2" class="w-6 h-6"></i>
                        </div>
                        <h3 class="text-sm font-black text-slate-900">All Loadouts Covered</h3>
                        <p class="text-xs text-slate-500 mt-1">There are no dispatched shop order items missing invoice coverage.</p>
                    </div>
                @else
                    <div class="overflow-hidden bg-white rounded-3xl border border-slate-200/90 shadow-xs">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 border-b border-slate-200/80 text-[11px] font-black text-slate-500 uppercase tracking-wider">
                                <tr>
                                    <th class="px-4 py-3">Outlet / Shop</th>
                                    <th class="px-4 py-3">Date</th>
                                    <th class="px-4 py-3">Product</th>
                                    <th class="px-4 py-3 text-right">Dispatched Qty</th>
                                    <th class="px-4 py-3 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($loadoutNotBilled as $loadoutItem)
                                    <tr class="hover:bg-slate-50/60 transition-colors">
                                        <td class="px-4 py-3 font-black text-slate-900">
                                            {{ $loadoutItem->order?->shop?->name ?? 'Outlet Unassigned' }}
                                        </td>
                                        <td class="px-4 py-3 text-slate-600 font-medium">
                                            {{ $loadoutItem->order?->business_date?->format('d M Y') ?? $loadoutItem->created_at?->format('d M Y') }}
                                        </td>
                                        <td class="px-4 py-3 font-bold text-slate-800">
                                            {{ $loadoutItem->product?->name ?? 'Product #'.$loadoutItem->product_id }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-black text-slate-900">
                                            {{ (float) ($loadoutItem->delivered_qty ?: $loadoutItem->loaded_qty ?: $loadoutItem->requested_qty) }} {{ $loadoutItem->product?->unit ?? 'KG' }}
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-black text-rose-800">
                                                NOT BILLED
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $loadoutNotBilled->links() }}
                    </div>
                @endif
            </div>
        @endif
    </div>
@endsection
