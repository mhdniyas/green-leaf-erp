@extends('admin.cashbook.layouts.app')

@section('title', 'Settings — Green Leaf Ledger System')

@section('content')
<div class="space-y-8">

    {{-- PAGE HEADER with hierarchy context --}}
    <div class="flex items-start justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs font-medium mb-2">
                <span class="flex items-center gap-1 font-bold text-emerald-700">
                    <i data-lucide="leaf" class="w-3.5 h-3.5"></i> Green Leaf
                </span>
                <i data-lucide="chevron-right" class="w-3 h-3 text-slate-300"></i>
                @foreach($clients as $client)
                <span class="text-slate-600 font-semibold">{{ $client->name }}</span>
                <i data-lucide="chevron-right" class="w-3 h-3 text-slate-300"></i>
                <span class="text-slate-400">{{ $client->shops->count() }} shops</span>
                @endforeach
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Settings</h1>
            <p class="mt-1 text-sm text-slate-500">Manage system-wide configurations, presets, and shop assignments.</p>
        </div>
        <a href="{{ route('admin.cashbook.settings.presets') }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 text-xs font-bold text-white bg-slate-900 rounded-xl hover:bg-slate-800 transition shadow-sm">
            <i data-lucide="layers" class="w-3.5 h-3.5"></i>
            Manage Presets
        </a>
    </div>

    {{-- SECTION: CLIENTS (Green Leaf's client list) --}}
    <div>
        <h2 class="text-xs font-extrabold uppercase tracking-wider text-slate-400 mb-4 flex items-center gap-2">
            <i data-lucide="briefcase" class="w-3.5 h-3.5"></i> Clients under Green Leaf
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
            @foreach($clients as $client)
            <div class="white-card rounded-2xl p-5 border-l-4 border-l-emerald-500">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="h-9 w-9 rounded-xl bg-emerald-50 border border-emerald-200 flex items-center justify-center">
                            <i data-lucide="briefcase" class="w-4 h-4 text-emerald-700"></i>
                        </div>
                        <div>
                            <div class="text-sm font-extrabold text-slate-900">{{ $client->name }}</div>
                            <div class="text-[10px] text-slate-500 font-medium">{{ $client->slug }}</div>
                        </div>
                    </div>
                    <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-1 rounded-lg">
                        {{ $client->shops->count() }} shops
                    </span>
                </div>
                @if($client->address)
                <p class="text-xs text-slate-500 mb-3">{{ $client->address }}</p>
                @endif
                <div class="flex flex-wrap gap-1.5">
                    @foreach($client->shops->take(6) as $shop)
                    <a href="{{ route('admin.cashbook.shop.show', $shop->slug ?? $shop->shop_id) }}"
                       class="text-[10px] font-bold px-2 py-1 bg-slate-100 text-slate-700 border border-slate-200 rounded-full hover:bg-slate-200 transition">
                        {{ $shop->name }}
                    </a>
                    @endforeach
                    @if($client->shops->count() > 6)
                    <span class="text-[10px] text-slate-400 font-medium self-center">+{{ $client->shops->count() - 6 }} more</span>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- SECTION: PRESET OVERVIEW CARDS --}}
    <div>
        <h2 class="text-xs font-extrabold uppercase tracking-wider text-slate-400 mb-4">Preset Configurations</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
            @foreach($presets as $preset)
            <div class="white-card rounded-2xl p-5 white-card-hover transition-all">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="h-9 w-9 rounded-xl bg-slate-100 flex items-center justify-center">
                            <i data-lucide="layers" class="w-4 h-4 text-slate-700"></i>
                        </div>
                        <div>
                            <div class="text-sm font-extrabold text-slate-900">{{ $preset->name }}</div>
                            @if($preset->is_default)
                            <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full">Default</span>
                            @endif
                        </div>
                    </div>
                    <span class="text-[10px] font-bold text-slate-500 bg-slate-100 px-2 py-1 rounded-lg">
                        {{ $preset->shops->count() }} shop{{ $preset->shops->count() !== 1 ? 's' : '' }}
                    </span>
                </div>
                @if($preset->description)
                <p class="text-xs text-slate-500 mb-4 leading-relaxed">{{ $preset->description }}</p>
                @endif

                {{-- Entry type chips --}}
                <div class="flex flex-wrap gap-1.5 mb-4">
                    @foreach($preset->entrySettings->where('enabled', true)->sortBy('display_order') as $es)
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 border border-slate-200">
                        {{ $es->entryType->name ?? $es->entryType->code ?? '—' }}
                    </span>
                    @endforeach
                </div>

                {{-- Assigned shops --}}
                @if($preset->shops->count() > 0)
                <div class="border-t border-slate-100 pt-3">
                    <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 mb-2">Assigned Shops</div>
                    <div class="space-y-1">
                        @foreach($preset->shops->take(4) as $shop)
                        <div class="flex items-center gap-2 text-xs text-slate-600">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                            <span class="font-medium">{{ $shop->name }}</span>
                            <span class="text-slate-400 font-mono text-[10px]">{{ $shop->code }}</span>
                        </div>
                        @endforeach
                        @if($preset->shops->count() > 4)
                        <div class="text-[10px] text-slate-400 font-medium pl-3.5">+{{ $preset->shops->count() - 4 }} more</div>
                        @endif
                    </div>
                </div>
                @endif
            </div>
            @endforeach
        </div>
    </div>

    {{-- SECTION: SHOP ASSIGNMENTS --}}
    <div>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xs font-extrabold uppercase tracking-wider text-slate-400">Shop → Preset Assignments</h2>
            <span class="text-[10px] text-slate-400 font-medium">{{ $shops->count() }} shops</span>
        </div>
        <div class="white-card rounded-2xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100">
                        <th class="px-5 py-3.5 text-left text-[10px] font-extrabold uppercase tracking-wider text-slate-400">#</th>
                        <th class="px-5 py-3.5 text-left text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Shop</th>
                        <th class="px-5 py-3.5 text-left text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Code</th>
                        <th class="px-5 py-3.5 text-left text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Assigned Preset</th>
                        <th class="px-5 py-3.5 text-left text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Change Preset</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @foreach($shops as $shop)
                    <tr class="hover:bg-slate-50/60 transition-colors" id="shop-row-{{ $shop->shop_id }}">
                        <td class="px-5 py-3.5 text-xs font-mono text-slate-400">{{ $shop->shop_id }}</td>
                        <td class="px-5 py-3.5">
                            <a href="{{ route('admin.cashbook.shop.show', $shop->slug ?? $shop->shop_id) }}"
                               class="font-bold text-slate-900 hover:text-brand-600 transition-colors text-xs">
                                {{ $shop->name }}
                            </a>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="font-mono text-[10px] text-slate-500 bg-slate-100 px-2 py-0.5 rounded">{{ $shop->code }}</span>
                        </td>
                        <td class="px-5 py-3.5" id="preset-label-{{ $shop->shop_id }}">
                            @if($shop->preset)
                                <span class="text-xs font-bold text-slate-700 flex items-center gap-1.5">
                                    <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                                    {{ $shop->preset->name }}
                                </span>
                            @else
                                <span class="text-xs text-slate-400 italic">No preset assigned</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-2">
                                <select id="preset-select-{{ $shop->shop_id }}"
                                        class="text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-slate-900 cursor-pointer">
                                    <option value="">— None —</option>
                                    @foreach($presets as $preset)
                                    <option value="{{ $preset->id }}" {{ $shop->preset_id == $preset->id ? 'selected' : '' }}>
                                        {{ $preset->name }}
                                    </option>
                                    @endforeach
                                </select>
                                <button onclick="assignPreset({{ $shop->shop_id }})"
                                        class="text-[10px] font-extrabold px-3 py-1.5 bg-slate-900 text-white rounded-lg hover:bg-slate-700 transition">
                                    Save
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
async function assignPreset(shopId) {
    const select = document.getElementById('preset-select-' + shopId);
    const presetId = select.value || null;
    const presetName = select.options[select.selectedIndex].text;

    try {
        const res = await fetch('/admin/cashbook/api/assign-preset', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ shop_id: shopId, preset_id: presetId }),
        });
        const data = await res.json();
        if (data.success) {
            const label = document.getElementById('preset-label-' + shopId);
            if (presetId) {
                label.innerHTML = `<span class="text-xs font-bold text-slate-700 flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-emerald-400"></span>${presetName}</span>`;
            } else {
                label.innerHTML = `<span class="text-xs text-slate-400 italic">No preset assigned</span>`;
            }
            showToast(data.message, 'success');
        } else {
            showToast(data.message, 'error');
        }
    } catch (e) {
        showToast('Failed to assign preset', 'error');
    }
}
</script>
@endpush
