@extends('purchase-manager.layouts.app')

@section('title', 'Purchasing Dashboard')
@section('page_title', 'Purchasing Dashboard')
@section('page_description', 'Only the key numbers for today and tomorrow, with one direct path to approve shop orders.')

@section('content')
    <div class="space-y-6">
        <section class="overflow-hidden rounded-[2.5rem] bg-slate-950 text-white shadow-[0_24px_60px_rgba(15,23,42,0.28)]">
            <div class="bg-[radial-gradient(circle_at_top_left,_rgba(34,211,238,0.22),_transparent_38%),linear-gradient(135deg,_#020617_0%,_#0f172a_55%,_#082f49_100%)] px-5 py-6 sm:px-7 sm:py-7">
                <p class="text-[11px] font-black uppercase tracking-[0.22em] text-cyan-300">Purchase Manager</p>
                <h2 class="mt-2 text-2xl font-black tracking-tight sm:text-3xl">Daily order control</h2>
                <p class="mt-2 max-w-xl text-sm font-medium text-slate-300">See tomorrow&apos;s total shop orders, confirm how many deliveries were completed today, and move straight into approval.</p>
            </div>
        </section>

        <!-- Search Purchase Orders Section -->
        <section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-100 pb-4">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-cyan-700">Lookup</p>
                    <h3 class="mt-1 text-xl font-black text-slate-950">Search Purchase Orders</h3>
                    <p class="mt-1 text-xs font-semibold text-slate-500">Find any PO by PO number, Supplier, Product, or GRN reference.</p>
                </div>
            </div>

            <form method="GET" action="{{ route('purchasing.orders.index') }}" class="mt-4 flex flex-wrap items-center gap-3">
                <div class="relative flex-1 min-w-[240px]">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                    </div>
                    <input type="text"
                           name="search"
                           value="{{ $search ?? '' }}"
                           placeholder="Search PO # (e.g. PO-PURCH-20260911-70A5), Supplier, or Product..."
                           class="w-full rounded-2xl border border-slate-200 bg-slate-50/70 pl-10 pr-4 py-2.5 text-xs font-bold text-slate-900 placeholder:text-slate-400 focus:bg-white focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition shadow-2xs">
                </div>

                <div class="relative">
                    <input type="date"
                           name="date"
                           value="{{ $searchDate ?? '' }}"
                           class="rounded-2xl border border-slate-200 bg-slate-50/70 px-3.5 py-2.5 text-xs font-bold text-slate-800 focus:bg-white focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition shadow-2xs cursor-pointer">
                </div>

                <button type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-2xl bg-cyan-600 hover:bg-cyan-700 px-5 py-2.5 text-xs font-black text-white transition shadow-sm hover:shadow cursor-pointer">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                    <span>Search</span>
                </button>

                @if(!empty($search) || !empty($searchDate) || !empty($searchStatus))
                    <a href="{{ route('purchasing.orders.index') }}"
                       class="inline-flex items-center justify-center px-4 py-2.5 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold transition">
                        Clear
                    </a>
                @endif
            </form>

            @if($searchResults !== null)
                <div class="mt-5 border-t border-slate-100 pt-4">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="text-xs font-black uppercase tracking-wider text-slate-700">Search Results ({{ $searchResults->total() }})</h4>
                    </div>

                    @if($searchResults->isEmpty())
                        <p class="text-xs font-semibold text-slate-500 bg-slate-50 p-4 rounded-xl">No purchase orders found matching your search criteria.</p>
                    @else
                        <div class="overflow-hidden rounded-xl border border-slate-200">
                            <div class="overflow-x-auto">
                                <table class="w-full border-collapse text-left text-xs text-slate-600">
                                    <thead class="bg-slate-50 text-[11px] font-black uppercase tracking-wider text-slate-700 border-b border-slate-200">
                                        <tr>
                                            <th class="px-4 py-3">PO Number</th>
                                            <th class="px-4 py-3">Date</th>
                                            <th class="px-4 py-3">Supplier</th>
                                            <th class="px-4 py-3">Status</th>
                                            <th class="px-4 py-3">Items</th>
                                            <th class="px-4 py-3">GRN #</th>
                                            <th class="px-4 py-3 text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white font-medium">
                                        @foreach($searchResults as $po)
                                            <tr class="hover:bg-slate-50/60 transition-colors">
                                                <td class="px-4 py-3 font-bold text-slate-900 whitespace-nowrap">
                                                    <a href="{{ route('purchasing.orders.show', $po) }}" class="text-cyan-700 hover:text-cyan-900 hover:underline">
                                                        {{ $po->po_number }}
                                                    </a>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap font-semibold text-slate-700">
                                                    {{ $po->order_date?->format('d M Y') }}
                                                </td>
                                                <td class="px-4 py-3 font-bold text-slate-800">
                                                    {{ $po->supplier?->name ?? 'N/A' }}
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider border {{ $po->status?->color() ?? 'bg-slate-100 text-slate-700 border-slate-200' }}">
                                                        {{ $po->status?->label() ?? $po->status?->value ?? 'N/A' }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <div class="flex flex-wrap gap-1 max-w-xs">
                                                        @foreach($po->items->take(3) as $poItem)
                                                            <span class="inline-flex items-center rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-700">
                                                                {{ $poItem->product?->name }}: {{ (float)$poItem->quantity }} {{ $poItem->product?->unit }}
                                                            </span>
                                                        @endforeach
                                                        @if($po->items->count() > 3)
                                                            <span class="text-[10px] text-slate-400 font-bold">+{{ $po->items->count() - 3 }} more</span>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap">
                                                    @if($po->goodsReceiveds->isNotEmpty())
                                                        <div class="flex flex-col gap-0.5">
                                                            @foreach($po->goodsReceiveds as $grn)
                                                                <a href="{{ route('purchasing.grns.show', $grn) }}" class="text-[11px] font-mono font-bold text-indigo-600 hover:text-indigo-800 hover:underline">
                                                                    {{ $grn->grn_number }}
                                                                </a>
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        <span class="text-slate-400 text-xs">—</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                                    <a href="{{ route('purchasing.orders.show', $po) }}"
                                                       class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition shadow-2xs">
                                                        <span>View</span>
                                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        @if($searchResults->hasPages())
                            <div class="mt-4">
                                {!! $searchResults->links() !!}
                            </div>
                        @endif
                    @endif
                </div>
            @endif
        </section>

        <section class="grid grid-cols-2 gap-3 sm:gap-4">
            <article class="rounded-[1.75rem] border border-slate-200 bg-white p-4 shadow-sm sm:rounded-[2rem] sm:p-5">
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-cyan-700">Tomorrow</p>
                <h3 class="mt-2 text-base font-black text-slate-950 sm:text-lg">Total shop orders</h3>
                <p class="mt-1 text-xs font-semibold text-slate-500 sm:text-sm">{{ \Illuminate\Support\Carbon::parse($tomorrowDate)->format('d M Y') }}</p>
                <p class="mt-5 text-4xl font-black tracking-tight text-slate-950 sm:mt-6 sm:text-5xl">{{ $tomorrowShopOrdersCount }}</p>
                <p class="mt-3 text-xs font-semibold text-slate-600 sm:text-sm">{{ $tomorrowOrdersAwaitingApprovalCount }} waiting for approval.</p>
            </article>

            <article class="rounded-[1.75rem] border border-slate-200 bg-white p-4 shadow-sm sm:rounded-[2rem] sm:p-5">
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-700">Today</p>
                <h3 class="mt-2 text-base font-black text-slate-950 sm:text-lg">Delivery done</h3>
                <p class="mt-1 text-xs font-semibold text-slate-500 sm:text-sm">{{ \Illuminate\Support\Carbon::parse($todayDate)->format('d M Y') }}</p>
                <p class="mt-5 text-4xl font-black tracking-tight text-slate-950 sm:mt-6 sm:text-5xl">{{ $todayDeliveredOrdersCount }}</p>
                <p class="mt-3 text-xs font-semibold text-slate-600 sm:text-sm">Completed shop deliveries recorded today.</p>
            </article>
        </section>

        <section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-500">Shortcut</p>
                    <h3 class="mt-2 text-xl font-black text-slate-950">Approve shop orders</h3>
                    <p class="mt-2 text-sm font-semibold text-slate-600">Open tomorrow&apos;s approval board directly and clear pending shop requests.</p>
                </div>
                <a href="{{ route('requisitions.board', ['date' => $tomorrowDate]) }}"
                    class="inline-flex items-center justify-center rounded-2xl bg-cyan-500 px-5 py-3 text-sm font-black text-white transition hover:bg-cyan-600">
                    Open Approve Shop Orders
                </a>
            </div>

            <div class="mt-5 flex flex-col gap-3 rounded-[1.5rem] border border-emerald-100 bg-emerald-50/70 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-black text-emerald-950">Edit daily shop orders</p>
                    <p class="mt-1 text-xs font-semibold text-emerald-800">Open the shop marketplace for any date and add or update products like a shop incharge.</p>
                </div>
                <a href="{{ route('purchasing.shop-orders.index', ['date' => $tomorrowDate]) }}"
                    class="inline-flex items-center justify-center rounded-2xl bg-emerald-600 px-5 py-3 text-sm font-black text-white transition hover:bg-emerald-700">
                    Edit Shop Orders
                </a>
            </div>

            <div class="mt-5 rounded-[1.5rem] bg-slate-50 p-4">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-black text-slate-900">Delivered shops today</p>
                    <span class="rounded-full bg-white px-3 py-1 text-[11px] font-black uppercase tracking-[0.16em] text-slate-600">{{ $todayDeliveredOrdersCount }}</span>
                </div>

                @if ($recentDeliveredShops->isEmpty())
                    <p class="mt-3 text-sm font-semibold text-slate-500">No deliveries were completed today yet.</p>
                @else
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($recentDeliveredShops as $order)
                            <span class="rounded-full bg-white px-3 py-2 text-xs font-black text-slate-700 shadow-sm">
                                {{ $order->shop?->name ?? 'Unknown Shop' }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        <section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
            <div>
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-red-500">History</p>
                <h3 class="mt-2 text-xl font-black text-slate-950">Cancelled purchases</h3>
                <p class="mt-2 text-sm font-semibold text-slate-600">Past draft purchaser carts and unfulfilled purchase orders that were automatically cancelled.</p>
            </div>

            @if ($cancelledCarts->isEmpty() && $cancelledPOs->isEmpty())
                <p class="mt-5 text-sm font-semibold text-slate-500 bg-slate-50 p-4 rounded-[1.5rem]">No cancelled purchases found.</p>
            @else
                <div class="mt-5 overflow-hidden rounded-[1.5rem] border border-slate-200">
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-left text-sm text-slate-600">
                            <thead class="bg-slate-50 text-xs font-black uppercase tracking-wider text-slate-700">
                                <tr>
                                    <th class="px-4 py-3 sm:px-6">Type / Number</th>
                                    <th class="px-4 py-3 sm:px-6">Date</th>
                                    <th class="px-4 py-3 sm:px-6">Purchaser</th>
                                    <th class="px-4 py-3 sm:px-6">Supplier</th>
                                    <th class="px-4 py-3 sm:px-6">Items</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white font-medium">
                                @foreach ($cancelledCarts as $cart)
                                    <tr class="hover:bg-slate-50/50">
                                        <td class="whitespace-nowrap px-4 py-4 sm:px-6">
                                            <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600 ring-1 ring-inset ring-slate-500/10 mr-1.5">Cart</span>
                                            <span class="font-black text-slate-950">{{ $cart->cart_number }}</span>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-4 sm:px-6 text-xs">
                                            {{ $cart->business_date?->format('d M Y') }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-4 sm:px-6 text-xs text-slate-900">
                                            {{ $cart->user?->name ?? 'N/A' }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-4 sm:px-6 text-xs">
                                            {{ $cart->supplier?->name ?? 'N/A' }}
                                        </td>
                                        <td class="px-4 py-4 sm:px-6 text-xs">
                                            @if ($cart->items->isEmpty())
                                                <span class="text-slate-400">No items</span>
                                            @else
                                                <div class="flex flex-wrap gap-1">
                                                    @foreach ($cart->items as $item)
                                                        <span class="inline-flex items-center rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-700 ring-1 ring-inset ring-red-600/10">
                                                            {{ $item->product?->name ?? 'Unknown' }}: {{ (float)$item->quantity }} {{ $item->product?->unit ?? '' }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach

                                @foreach ($cancelledPOs as $po)
                                    <tr class="hover:bg-slate-50/50">
                                        <td class="whitespace-nowrap px-4 py-4 sm:px-6">
                                            <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600 ring-1 ring-inset ring-slate-500/10 mr-1.5">PO</span>
                                            <a href="{{ route('purchasing.orders.show', $po) }}" class="font-black text-cyan-600 hover:text-cyan-700 underline">{{ $po->po_number }}</a>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-4 sm:px-6 text-xs">
                                            {{ $po->order_date?->format('d M Y') }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-4 sm:px-6 text-xs text-slate-900">
                                            {{ $po->createdBy?->name ?? 'N/A' }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-4 sm:px-6 text-xs">
                                            {{ $po->supplier?->name ?? 'N/A' }}
                                        </td>
                                        <td class="px-4 py-4 sm:px-6 text-xs">
                                            @if ($po->items->isEmpty())
                                                <span class="text-slate-400">No items</span>
                                            @else
                                                <div class="flex flex-wrap gap-1">
                                                    @foreach ($po->items as $item)
                                                        <span class="inline-flex items-center rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-700 ring-1 ring-inset ring-red-600/10">
                                                            {{ $item->product?->name ?? 'Unknown' }}: {{ (float)$item->quantity }} {{ $item->product?->unit ?? '' }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Pagination --}}
                @if ($cancelledCarts->hasPages() || $cancelledPOs->hasPages())
                    <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between px-1">
                        @if ($cancelledCarts->hasPages())
                            <div class="text-xs font-semibold text-slate-500">
                                <span class="font-black text-slate-700">Carts</span> — page {{ $cancelledCarts->currentPage() }} of {{ $cancelledCarts->lastPage() }}
                                &nbsp;
                                {!! $cancelledCarts->links() !!}
                            </div>
                        @endif
                        @if ($cancelledPOs->hasPages())
                            <div class="text-xs font-semibold text-slate-500">
                                <span class="font-black text-slate-700">Purchase Orders</span> — page {{ $cancelledPOs->currentPage() }} of {{ $cancelledPOs->lastPage() }}
                                &nbsp;
                                {!! $cancelledPOs->links() !!}
                            </div>
                        @endif
                    </div>
                @endif
            @endif
        </section>
    </div>
@endsection
