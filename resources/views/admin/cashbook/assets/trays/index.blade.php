@extends('admin.cashbook.layouts.app')

@section('title', 'Tray Assets Management')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Tray Assets</h1>
            <p class="text-sm text-slate-500 font-medium">Manage tray types, monitor shop balances, and warehouse availability.</p>
        </div>
        <div>
            <button onclick="document.getElementById('add-tray-modal').classList.remove('hidden')" class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white text-sm font-bold rounded-xl shadow-sm transition">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                Add Tray Type
            </button>
        </div>
    </div>

    <!-- Overview Stat Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Total Owned Trays</p>
                <p class="text-3xl font-black text-slate-900 mt-1">{{ number_format($totalOwned) }}</p>
            </div>
            <div class="h-12 w-12 rounded-xl bg-slate-100 flex items-center justify-center text-slate-700">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" /></svg>
            </div>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-blue-600">With Shops (Held)</p>
                <p class="text-3xl font-black text-blue-600 mt-1">{{ number_format($totalWithShops) }}</p>
            </div>
            <div class="h-12 w-12 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72m-13.5 8.65h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z" /></svg>
            </div>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-emerald-600">In Warehouse (Available)</p>
                <p class="text-3xl font-black text-emerald-600 mt-1">{{ number_format($totalInWarehouse) }}</p>
            </div>
            <div class="h-12 w-12 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" /></svg>
            </div>
        </div>
    </div>

    <!-- Main Table Card -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-base font-extrabold text-slate-900">Tray Inventory Overview</h2>
            <span class="text-xs font-semibold px-2.5 py-1 bg-slate-100 text-slate-600 rounded-lg">{{ count($trays) }} Types</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-700 uppercase font-black text-[11px] tracking-wider border-b border-slate-100">
                    <tr>
                        <th class="py-3.5 px-5">Tray Name</th>
                        <th class="py-3.5 px-5 text-right">Total Owned</th>
                        <th class="py-3.5 px-5 text-right">With Shops</th>
                        <th class="py-3.5 px-5 text-right">In Warehouse</th>
                        <th class="py-3.5 px-5 text-center">Status</th>
                        <th class="py-3.5 px-5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($trays as $tray)
                    <tr class="hover:bg-slate-50/80 transition">
                        <td class="py-4 px-5">
                            <div class="flex items-center gap-3">
                                <div class="h-8 w-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-xs">
                                    {{ substr($tray['name'], 0, 2) }}
                                </div>
                                <span class="font-bold text-slate-900">{{ $tray['name'] }}</span>
                            </div>
                        </td>
                        <td class="py-4 px-5 text-right font-extrabold text-slate-900">
                            {{ number_format($tray['total_owned']) }}
                        </td>
                        <td class="py-4 px-5 text-right font-extrabold text-blue-600">
                            {{ number_format($tray['with_shops']) }}
                        </td>
                        <td class="py-4 px-5 text-right font-extrabold text-emerald-600">
                            {{ number_format($tray['in_warehouse']) }}
                        </td>
                        <td class="py-4 px-5 text-center">
                            @if($tray['is_active'])
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                Active
                            </span>
                            @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600">
                                Disabled
                            </span>
                            @endif
                        </td>
                        <td class="py-4 px-5 text-right">
                            <div class="inline-flex items-center gap-2">
                                <a href="{{ route('admin.cashbook.assets.trays.show', $tray['id']) }}" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold rounded-lg transition">
                                    View
                                </a>
                                <a href="{{ route('admin.cashbook.assets.trays.edit', $tray['id']) }}" class="px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 text-xs font-bold rounded-lg transition">
                                    Edit
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-8 text-center text-slate-400 font-medium">
                            No tray types configured yet.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-slate-900 text-white font-black text-xs">
                    <tr>
                        <td class="py-4 px-5 uppercase tracking-wider">TOTAL</td>
                        <td class="py-4 px-5 text-right">{{ number_format($totalOwned) }}</td>
                        <td class="py-4 px-5 text-right text-blue-300">{{ number_format($totalWithShops) }}</td>
                        <td class="py-4 px-5 text-right text-emerald-300">{{ number_format($totalInWarehouse) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Add Tray Modal -->
<div id="add-tray-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <h3 class="text-lg font-black text-slate-900">Add New Tray Type</h3>
            <button onclick="document.getElementById('add-tray-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>

        <form action="{{ route('admin.cashbook.assets.trays.store') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Tray Name</label>
                <input type="text" name="name" required placeholder="e.g. Small Tray" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm font-semibold focus:ring-2 focus:ring-slate-900 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Total Owned (Company Asset)</label>
                <input type="number" name="total_owned" required min="0" value="0" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm font-semibold focus:ring-2 focus:ring-slate-900 focus:outline-none">
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_active" id="add_is_active" value="1" checked class="rounded text-slate-900 focus:ring-slate-900">
                <label for="add_is_active" class="text-sm font-bold text-slate-700">Active (Visible in Daily Loadout)</label>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('add-tray-modal').classList.add('hidden')" class="px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-100 rounded-xl transition">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 bg-slate-900 hover:bg-slate-800 text-white text-sm font-bold rounded-xl shadow-sm transition">
                    Save Tray Type
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
