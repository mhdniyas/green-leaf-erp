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
            @endif
        </section>
    </div>
@endsection
