@extends('purchase-manager.layouts.app')

@section('title', 'Purchase Manager Dashboard')
@section('page_title', 'Purchase Manager Dashboard')
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
    </div>
@endsection
