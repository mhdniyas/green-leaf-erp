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
    activeNetBalanceUuid: @js($relations->firstWhere('is_net_balance', true)?->public_uuid),
    activePaymentPayableUuid: @js($relations->firstWhere('is_payment_payable', true)?->public_uuid),
    activePaymentPaidUuid: @js($relations->firstWhere('is_payment_paid', true)?->public_uuid),
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
    },
    async setNetBalance(uuid) {
        try {
            const res = await fetch('{{ url('admin/cashbook/settings/shops/'.$shopKey.'/settlements') }}/' + uuid + '/set-net-balance', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=\'csrf-token\']')?.getAttribute('content') || '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const data = await res.json();
            if (data.success) {
                this.activeNetBalanceUuid = uuid;
                if (typeof showToast === 'function') {
                    showToast(data.message || 'Net Balance settlement updated', 'success');
                }
            } else {
                alert(data.message || 'Failed to update Net Balance');
            }
        } catch (e) {
            console.error(e);
            alert('Failed to set Net Balance.');
        }
    },
    async setPaymentPayable(uuid) {
        try {
            const res = await fetch('{{ url('admin/cashbook/settings/shops/'.$shopKey.'/settlements') }}/' + uuid + '/set-payment-payable', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=\'csrf-token\']')?.getAttribute('content') || '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const data = await res.json();
            if (data.success) {
                this.activePaymentPayableUuid = uuid;
                if (typeof showToast === 'function') {
                    showToast(data.message || 'Default Payment Payable updated', 'success');
                }
            } else {
                alert(data.message || 'Failed to update Default Payment Payable');
            }
        } catch (e) {
            console.error(e);
            alert('Failed to set Default Payment Payable.');
        }
    },
    async setPaymentPaid(uuid) {
        try {
            const res = await fetch('{{ url('admin/cashbook/settings/shops/'.$shopKey.'/settlements') }}/' + uuid + '/set-payment-paid', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=\'csrf-token\']')?.getAttribute('content') || '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            const data = await res.json();
            if (data.success) {
                this.activePaymentPaidUuid = uuid;
                if (typeof showToast === 'function') {
                    showToast(data.message || 'Default Payment Paid updated', 'success');
                }
            } else {
                alert(data.message || 'Failed to update Default Payment Paid');
            }
        } catch (e) {
            console.error(e);
            alert('Failed to set Default Payment Paid.');
        }
    }
}">
    @include('admin.cashbook.settings.partials.tabs', [
        'activeTab' => 'settlements',
        'shopKey' => $shopKey,
        'currentShop' => $currentShop
    ])

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-xs font-bold uppercase tracking-wider text-indigo-700">{{ $currentShop->name }}</p>
            <h1 class="mt-1 text-3xl font-extrabold text-slate-950">Settlements</h1>
            <p class="mt-2 text-sm text-slate-600">Drag to reorder settlement calculation order. Configure formulas and Net Balance.</p>
        </div>
        <a href="{{ route('admin.cashbook.settings.shop.settlements.create', $shopKey) }}" class="shrink-0 rounded-xl bg-indigo-700 px-5 py-3 text-center text-sm font-bold text-white hover:bg-indigo-800">Create Settlement</a>
    </div>

    @if(session('success'))
        <p role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</p>
    @endif
    @if(session('error'))
        <p role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ session('error') }}</p>
    @endif

    <div id="settlement-status-bar" class="hidden rounded-xl border border-indigo-200 bg-indigo-50/80 px-4 py-2.5 text-xs font-bold text-indigo-900 transition flex items-center justify-between">
        <span id="settlement-status-text">Saving order...</span>
    </div>

    <div id="settlements-drag-container" class="space-y-4">
        @foreach($relations as $index => $settlement)
            <article data-uuid="{{ $settlement->public_uuid }}" draggable="true" class="settlement-item-card group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:shadow-md hover:border-indigo-200">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div class="flex items-start gap-3 min-w-0">
                        <!-- Drag Handle & Order Controls -->
                        <div class="flex flex-col items-center gap-1 shrink-0 pt-0.5 select-none">
                            <div class="drag-handle cursor-grab active:cursor-grabbing p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition" title="Drag to reorder">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M7 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 2zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 14zm6-12a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 2zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 14z"/>
                                </svg>
                            </div>
                            <span class="settlement-order-badge px-2 py-0.5 rounded-md bg-slate-100 border border-slate-200 text-[11px] font-mono font-bold text-slate-600">#{{ $index + 1 }}</span>
                            <div class="flex items-center gap-0.5 sm:hidden">
                                <button type="button" onclick="moveSettlementCard(this, -1)" class="p-1 rounded text-slate-400 hover:text-slate-700 text-xs font-bold" title="Move Up">&uarr;</button>
                                <button type="button" onclick="moveSettlementCard(this, 1)" class="p-1 rounded text-slate-400 hover:text-slate-700 text-xs font-bold" title="Move Down">&darr;</button>
                            </div>
                        </div>

                        <div class="min-w-0 space-y-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h2 class="break-words text-lg font-extrabold text-slate-950">{{ $settlement->name }}</h2>

                                <!-- Net Balance Badge -->
                                <template x-if="activeNetBalanceUuid === '{{ $settlement->public_uuid }}'">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-black uppercase tracking-wider text-emerald-900 border border-emerald-300">★ Net Balance</span>
                                </template>

                                <!-- Payment Payable Badge -->
                                <template x-if="activePaymentPayableUuid === '{{ $settlement->public_uuid }}'">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-indigo-100 px-2.5 py-0.5 text-[11px] font-black uppercase tracking-wider text-indigo-900 border border-indigo-300">Payments: Payable</span>
                                 </template>

                                <!-- Payment Paid Badge -->
                                <template x-if="activePaymentPaidUuid === '{{ $settlement->public_uuid }}'">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-black uppercase tracking-wider text-emerald-900 border border-emerald-300">Payments: Paid</span>
                                </template>

                                @if($settlement->is_company_payable)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wider text-slate-700 border border-slate-300">Company Payable</span>
                                @endif
                            </div>
                            <p class="text-xs font-bold {{ $settlement->enabled ? 'text-emerald-700' : 'text-slate-500' }}">
                                {{ $settlement->enabled ? 'Shown in summary' : 'Hidden from summary' }}{{ str_starts_with($settlement->relation_type, 'default_') ? ' · Default settlement' : '' }}
                            </p>
                        </div>
                    </div>

                    <!-- Right Action Buttons -->
                    <div class="flex items-center gap-2 shrink-0 flex-wrap sm:flex-nowrap">
                        <template x-if="activeNetBalanceUuid !== '{{ $settlement->public_uuid }}'">
                            <button type="button" @click="setNetBalance('{{ $settlement->public_uuid }}')" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-emerald-50 hover:text-emerald-800 hover:border-emerald-300 transition" title="Designate this settlement as the shop's primary Net Balance">
                                Net Balance
                            </button>
                        </template>

                        <template x-if="activePaymentPayableUuid !== '{{ $settlement->public_uuid }}'">
                            <button type="button" @click="setPaymentPayable('{{ $settlement->public_uuid }}')" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-indigo-700 hover:bg-indigo-50 hover:border-indigo-300 transition" title="Use this settlement for Payments Payable">
                                Set Payable
                            </button>
                        </template>

                        <template x-if="activePaymentPaidUuid !== '{{ $settlement->public_uuid }}'">
                            <button type="button" @click="setPaymentPaid('{{ $settlement->public_uuid }}')" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-emerald-700 hover:bg-emerald-50 hover:border-emerald-300 transition" title="Use this settlement for Payments Paid">
                                Set Paid
                            </button>
                        </template>

                        @if($otherShops->isNotEmpty())
                            <button type="button" @click="openCopyModal({{ json_encode(['uuid' => $settlement->public_uuid, 'name' => $settlement->name]) }})" class="rounded-xl border border-indigo-200 bg-indigo-50 px-3.5 py-2 text-xs font-bold text-indigo-800 hover:bg-indigo-100 transition">
                                Copy to Shops &rarr;
                            </button>
                        @endif

                        <a href="{{ route('admin.cashbook.settings.shop.settlements.edit', [$shopKey, $settlement->public_uuid]) }}" aria-label="Edit {{ $settlement->name }}" class="rounded-xl border border-slate-300 px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Edit</a>

                        <form method="POST" action="{{ route('admin.cashbook.settings.shop.settlements.destroy', [$shopKey, $settlement->public_uuid]) }}" onsubmit="return confirm('Delete {{ addslashes($settlement->name) }}? This cannot be undone.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-xl border border-rose-300 px-4 py-2 text-xs font-bold text-rose-700 hover:bg-rose-50">Delete</button>
                        </form>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-2 rounded-xl bg-slate-50 p-3 text-sm" aria-label="Calculation formula">
                    @forelse($settlement->items as $item)
                        @php
                            $itemName = 'Unavailable category';
                            if ($item->source_settlement_id) {
                                $itemName = 'Settlement: ' . ($item->sourceSettlement?->name ?? ('#' . $item->source_settlement_id));
                            } elseif ($item->header_group_id) {
                                $hName = 'Header: ' . ($item->headerGroup?->name ?? ('#' . $item->header_group_id));
                                if ($item->header_mode === 'tagged_products_only') {
                                    $itemName = $hName . ' (Product Total Only)';
                                } elseif ($item->header_mode === 'categories_and_products') {
                                    $itemName = $hName . ' (Categories + Product Total)';
                                } else {
                                    $itemName = $hName . ' (All Categories Only)';
                                }
                            } elseif ($item->setting) {
                                $itemName = $item->setting->displayName();
                            }
                        @endphp
                        <span class="font-bold {{ $item->role === 'subtract' ? 'text-rose-700' : 'text-emerald-700' }}">{{ $item->role === 'subtract' ? '−' : '+' }}</span>
                        <span class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-slate-800">{{ $itemName }}</span>
                    @empty
                        <span class="text-slate-600">No categories selected. Edit to configure this settlement.</span>
                    @endforelse
                    @if($settlement->items->isNotEmpty())<span class="font-bold text-indigo-800">= {{ $settlement->name }}</span>@endif
                </div>
            </article>
        @endforeach
    </div>

    <p class="text-sm text-slate-600">Formulas apply to the selected day or period. Drag items to reorder how they are listed and computed. A category can be used in different settlements; results are shown separately.</p>

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

<script>
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('settlements-drag-container');
    if (!container) return;

    let draggedItem = null;

    function refreshBadges() {
        const cards = container.querySelectorAll('.settlement-item-card');
        cards.forEach((card, idx) => {
            const badge = card.querySelector('.settlement-order-badge');
            if (badge) badge.textContent = '#' + (idx + 1);
        });
    }

    async function saveOrder() {
        const cards = container.querySelectorAll('.settlement-item-card[data-uuid]');
        const order = Array.from(cards).map(card => card.getAttribute('data-uuid'));
        const statusBar = document.getElementById('settlement-status-bar');
        const statusText = document.getElementById('settlement-status-text');

        if (statusBar && statusText) {
            statusBar.classList.remove('hidden');
            statusText.textContent = 'Saving settlement order...';
        }

        try {
            const res = await fetch('{{ route('admin.cashbook.settings.shop.settlements.reorder', $shopKey) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ order })
            });
            const data = await res.json();
            if (statusBar && statusText) {
                if (data.success) {
                    statusText.textContent = 'Settlement order saved.';
                    setTimeout(() => { statusBar.classList.add('hidden'); }, 2000);
                } else {
                    statusText.textContent = data.message || 'Failed to save order';
                }
            }
        } catch (err) {
            console.error(err);
            if (statusBar && statusText) {
                statusText.textContent = 'Network error saving order.';
            }
        }
    }

    container.addEventListener('dragstart', (e) => {
        const card = e.target.closest('.settlement-item-card');
        if (!card) return;
        draggedItem = card;
        card.classList.add('opacity-40', 'border-indigo-400');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', card.getAttribute('data-uuid') || '');
    });

    container.addEventListener('dragend', (e) => {
        const card = e.target.closest('.settlement-item-card');
        if (card) {
            card.classList.remove('opacity-40', 'border-indigo-400');
        }
        container.querySelectorAll('.settlement-item-card').forEach(c => {
            c.classList.remove('border-t-4', 'border-indigo-600', 'bg-indigo-50/20');
        });
        draggedItem = null;
    });

    container.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        const target = e.target.closest('.settlement-item-card');
        if (!target || target === draggedItem) return;

        const rect = target.getBoundingClientRect();
        const next = (e.clientY - rect.top) / (rect.bottom - rect.top) > 0.5;
        container.insertBefore(draggedItem, next ? target.nextSibling : target);
        refreshBadges();
    });

    container.addEventListener('drop', (e) => {
        e.preventDefault();
        refreshBadges();
        saveOrder();
    });

    window.moveSettlementCard = function(btn, dir) {
        const card = btn.closest('.settlement-item-card');
        if (!card) return;
        if (dir === -1 && card.previousElementSibling) {
            container.insertBefore(card, card.previousElementSibling);
        } else if (dir === 1 && card.nextElementSibling) {
            container.insertBefore(card.nextElementSibling, card);
        }
        refreshBadges();
        saveOrder();
    };
});
</script>
@endsection
