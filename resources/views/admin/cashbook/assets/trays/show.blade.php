@extends('admin.cashbook.layouts.app')

@section('title', $trayType->name . ' — Tray Details')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.cashbook.assets.trays.index') }}" class="p-2 rounded-xl bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 transition">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
            </a>
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">{{ $trayType->name }}</h1>
                <p class="text-sm text-slate-500 font-medium">Tray inventory breakdown, shop distribution, and history.</p>
            </div>
        </div>
        <div>
            <a href="{{ route('admin.cashbook.assets.trays.edit', $trayType->id) }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white text-sm font-bold rounded-xl shadow-sm transition">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" /></svg>
                Edit Tray Type
            </a>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Total Owned</p>
            <p class="text-3xl font-black text-slate-900 mt-1">{{ number_format($totalOwned) }}</p>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-blue-600">With Shops (Held)</p>
            <p class="text-3xl font-black text-blue-600 mt-1">{{ number_format($withShops) }}</p>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-emerald-600">In Warehouse (Available)</p>
            <p class="text-3xl font-black text-emerald-600 mt-1">{{ number_format($inWarehouse) }}</p>
        </div>
    </div>

    <!-- Shop Balance Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-extrabold text-slate-900">Current Shop Balances</h2>
            <span class="text-xs font-semibold px-2.5 py-1 bg-blue-50 text-blue-700 rounded-lg">{{ count($shopBalances) }} Shops with History</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-700 uppercase font-black text-[11px] tracking-wider border-b border-slate-100">
                    <tr>
                        <th class="py-3.5 px-5">Shop</th>
                        <th class="py-3.5 px-5 text-right">Total Sent</th>
                        <th class="py-3.5 px-5 text-right">Total Returned</th>
                        <th class="py-3.5 px-5 text-right">Held (Balance)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($shopBalances as $bal)
                    <tr class="hover:bg-slate-50/80 transition">
                        <td class="py-4 px-5 font-bold text-slate-900">{{ $bal['shop_name'] }}</td>
                        <td class="py-4 px-5 text-right text-slate-700">{{ number_format($bal['sent']) }}</td>
                        <td class="py-4 px-5 text-right text-slate-700">{{ number_format($bal['returned']) }}</td>
                        <td class="py-4 px-5 text-right font-black {{ $bal['held'] > 0 ? 'text-blue-600' : 'text-slate-400' }}">
                            {{ number_format($bal['held']) }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="py-8 text-center text-slate-400 font-medium">
                            No shop movements recorded for this tray type yet.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Movement History Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-extrabold text-slate-900">Recent Movement History</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-700 uppercase font-black text-[11px] tracking-wider border-b border-slate-100">
                    <tr>
                        <th class="py-3.5 px-5">Date</th>
                        <th class="py-3.5 px-5">Shop</th>
                        <th class="py-3.5 px-5 text-right">Sent</th>
                        <th class="py-3.5 px-5 text-right">Returned</th>
                        <th class="py-3.5 px-5 text-right">Net</th>
                        <th class="py-3.5 px-5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($movements as $m)
                    <tr class="hover:bg-slate-50/80 transition">
                        <td class="py-3.5 px-5 font-semibold text-slate-700">{{ $m->date ? $m->date->format('Y-m-d') : '' }}</td>
                        <td class="py-3.5 px-5 font-bold text-slate-900">{{ $m->shop?->name ?? 'Shop #' . $m->shop_id }}</td>
                        <td class="py-3.5 px-5 text-right text-slate-700">{{ $m->sent_qty }}</td>
                        <td class="py-3.5 px-5 text-right text-slate-700">{{ $m->returned_qty }}</td>
                        <td class="py-3.5 px-5 text-right font-black {{ ($m->sent_qty - $m->returned_qty) > 0 ? 'text-blue-600' : 'text-slate-600' }}">
                            {{ $m->sent_qty - $m->returned_qty }}
                        </td>
                        <td class="py-3.5 px-5 text-right">
                            <a href="{{ route('admin.cashbook.assets.trays.shop-date.edit', ['shop' => $m->shop_id, 'date' => $m->date->format('Y-m-d')]) }}" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold rounded-lg transition">
                                Edit Day
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-8 text-center text-slate-400 font-medium">
                            No movements found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($movements->hasPages())
        <div class="p-4 border-t border-slate-100">
            {{ $movements->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
