@extends('admin.cashbook.layouts.app')
@section('title', $currentShop->name.' - Settlements')
@section('content')
@php
    $shopKey = $currentShop->slug ?: $currentShop->shop_id;
    $otherShops = $shops->filter(fn ($s) => (int) $s->shop_id !== (int) $currentShop->shop_id)->values();
@endphp
<div class="mx-auto max-w-5xl space-y-6" x-data="{
    copyModalOpen: false,
    activeSettlement: null,
    targetShops: [],
    selectAllShops: false,
    openCopyModal(settlement) {
        this.activeSettlement = settlement;
        this.targetShops = [];
        this.selectAllShops = false;
        this.copyModalOpen = true;
    },
    toggleSelectAll() {
        if (this.selectAllShops) {
            this.targetShops = @js($otherShops->pluck('shop_id')->map(fn ($id) => (int) $id)->all());
        } else {
            this.targetShops = [];
        }
    }
}">
    <a href="{{ route('admin.cashbook.settings.shop', $shopKey) }}" class="inline-flex py-2 text-sm font-bold text-slate-600 hover:text-indigo-700">&larr; Cashbook Settings</a>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-xs font-bold uppercase tracking-wider text-indigo-700">{{ $currentShop->name }}</p>
            <h1 class="mt-1 text-3xl font-extrabold text-slate-950">Settlements</h1>
            <p class="mt-2 text-sm text-slate-600">Choose how categories combine. Each enabled settlement appears as one result in the summary.</p>
        </div>
        <a href="{{ route('admin.cashbook.settings.shop.settlements.create', $shopKey) }}" class="shrink-0 rounded-xl bg-indigo-700 px-5 py-3 text-center text-sm font-bold text-white hover:bg-indigo-800">Create Settlement</a>
    </div>
    @if(session('success'))
        <p role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</p>
    @endif
    <div class="space-y-4">
        @foreach($relations as $settlement)
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="break-words text-lg font-extrabold text-slate-950">{{ $settlement->name }}</h2>
                        <p class="mt-1 text-xs font-bold {{ $settlement->enabled ? 'text-emerald-700' : 'text-slate-500' }}">{{ $settlement->enabled ? 'Shown in summary' : 'Hidden from summary' }}{{ str_starts_with($settlement->relation_type, 'default_') ? ' · Default settlement' : '' }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        @if($otherShops->isNotEmpty())
                            <button type="button" @click="openCopyModal({{ json_encode(['uuid' => $settlement->public_uuid, 'name' => $settlement->name]) }})" class="rounded-xl border border-indigo-200 bg-indigo-50 px-3.5 py-2 text-xs font-bold text-indigo-800 hover:bg-indigo-100 transition">
                                Copy to Shops &rarr;
                            </button>
                        @endif
                        <a href="{{ route('admin.cashbook.settings.shop.settlements.edit', [$shopKey, $settlement->public_uuid]) }}" aria-label="Edit {{ $settlement->name }}" class="rounded-xl border border-slate-300 px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Edit</a>
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap items-center gap-2 rounded-xl bg-slate-50 p-3 text-sm" aria-label="Calculation formula">
                    @forelse($settlement->items as $item)
                        <span class="font-bold {{ $item->role === 'subtract' ? 'text-rose-700' : 'text-emerald-700' }}">{{ $item->role === 'subtract' ? '−' : '+' }}</span>
                        <span class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-slate-800">{{ $item->setting?->displayName() ?? 'Unavailable category' }}</span>
                    @empty
                        <span class="text-slate-600">No categories selected. Edit to configure this settlement.</span>
                    @endforelse
                    @if($settlement->items->isNotEmpty())<span class="font-bold text-indigo-800">= {{ $settlement->name }}</span>@endif
                </div>
            </article>
        @endforeach
    </div>
    <p class="text-sm text-slate-600">Formulas apply to the selected day or period. A category can be used in different settlements; results are shown separately.</p>

    <!-- Copy Settlement to Other Shops Modal -->
    <div x-show="copyModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4" @click.self="copyModalOpen = false">
        <div class="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl space-y-4 animate-in fade-in zoom-in-95 duration-150">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-black text-slate-950">Copy Settlement</h3>
                    <p class="text-xs text-slate-500">Copy <span class="font-bold text-indigo-700" x-text="activeSettlement?.name"></span> formula to other shops.</p>
                </div>
                <button type="button" @click="copyModalOpen = false" class="text-slate-400 hover:text-slate-700 font-bold text-lg">&times;</button>
            </div>

            <form :action="'{{ url('admin/cashbook/settings/shops/'.$shopKey.'/settlements') }}/' + (activeSettlement?.uuid || '') + '/copy'" method="POST" class="space-y-4">
                @csrf
                <div class="space-y-3">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-600">Select Target Shops</span>
                        <label class="inline-flex items-center gap-1.5 text-xs font-bold text-indigo-700 cursor-pointer">
                            <input type="checkbox" x-model="selectAllShops" @change="toggleSelectAll()" class="rounded border-slate-300 text-indigo-700">
                            <span>Select All</span>
                        </label>
                    </div>

                    <div class="max-h-60 overflow-y-auto space-y-2 pr-1 divide-y divide-slate-100">
                        @foreach($otherShops as $otherShop)
                            <label class="flex items-center gap-3 pt-2 text-xs font-semibold text-slate-800 cursor-pointer hover:text-indigo-900">
                                <input type="checkbox" name="target_shop_ids[]" value="{{ $otherShop->shop_id }}" x-model="targetShops" class="rounded border-slate-300 text-indigo-700">
                                <span>{{ $otherShop->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="copyModalOpen = false" class="rounded-xl border border-slate-300 px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="submit" :disabled="targetShops.length === 0" class="rounded-xl bg-indigo-700 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-800 disabled:opacity-50">Copy Settlement</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

