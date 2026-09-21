@extends('admin.cashbook.layouts.app')

@section('title', 'Edit ' . $trayType->name)

@section('content')
<div class="max-w-xl mx-auto space-y-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('admin.cashbook.assets.trays.index') }}" class="p-2 rounded-xl bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 transition">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
        </a>
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Edit Tray Type</h1>
            <p class="text-sm text-slate-500 font-medium">Update name, total owned asset count, or status.</p>
        </div>
    </div>

    <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm">
        <form action="{{ route('admin.cashbook.assets.trays.update', $trayType->id) }}" method="POST" class="space-y-5">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Tray Name</label>
                <input type="text" name="name" required value="{{ old('name', $trayType->name) }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-semibold focus:ring-2 focus:ring-slate-900 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Total Owned (Company Asset)</label>
                <input type="number" name="total_owned" required min="0" value="{{ old('total_owned', $trayType->total_owned) }}" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-semibold focus:ring-2 focus:ring-slate-900 focus:outline-none">
                <p class="text-xs text-slate-400 mt-1 font-medium">With Shops: {{ $trayType->with_shops }} • In Warehouse: {{ $trayType->in_warehouse }}</p>
            </div>

            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/60">
                <div class="flex items-center gap-3">
                    <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $trayType->is_active) ? 'checked' : '' }} class="rounded text-slate-900 focus:ring-slate-900 h-4 w-4">
                    <div>
                        <label for="is_active" class="text-sm font-bold text-slate-800 cursor-pointer">Active in Loadout Operations</label>
                        <p class="text-xs text-slate-500">When disabled, this tray type will be hidden from daily dispatch but historical data is preserved.</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <a href="{{ route('admin.cashbook.assets.trays.index') }}" class="px-4 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-100 rounded-xl transition">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2.5 bg-slate-900 hover:bg-slate-800 text-white text-sm font-bold rounded-xl shadow-sm transition">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
