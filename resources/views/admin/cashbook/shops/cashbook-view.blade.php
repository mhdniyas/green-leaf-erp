@extends('admin.cashbook.layouts.app')

@section('title', ($currentShop->name ?: 'Shop').' — Shop Cashbook Layout')

@section('content')
@php
    $currentShopSlugOrId = $currentShop->slug ?: $currentShop->shop_id;
@endphp

<div class="mx-auto max-w-4xl space-y-6 pb-20">

    <!-- 1. HEADER & ACTIONS -->
    <header class="rounded-3xl border border-slate-200/80 bg-white p-4 sm:p-6 shadow-xs space-y-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-xl sm:text-2xl font-black text-slate-950 tracking-tight uppercase">
                        {{ $currentShop->name ?: 'Shop #'.$currentShop->shop_id }}
                    </h1>
                    <span class="inline-flex items-center rounded-full bg-emerald-100 text-emerald-800 px-3 py-0.5 text-xs font-black uppercase tracking-wider">
                        Shop Cashbook Layout
                    </span>
                </div>
                <p class="mt-1 text-xs font-semibold text-slate-500">
                    Arrange the main sections and categories to match how the Shop Owner views and enters their daily cashbook.
                </p>
            </div>

            <div class="flex items-center gap-2.5 flex-wrap">
                <a href="{{ route('admin.cashbook.shop.financial-ledger', $currentShopSlugOrId) }}"
                   class="inline-flex items-center gap-1.5 rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-black uppercase tracking-wider text-slate-700 shadow-2xs hover:bg-slate-50 transition cursor-pointer">
                    <span class="font-bold">&larr;</span>
                    <span>Financial Ledger</span>
                </a>

                <button type="button"
                        id="reset-layout-btn"
                        onclick="resetLayout()"
                        class="inline-flex items-center gap-1.5 rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-black uppercase tracking-wider text-slate-700 shadow-2xs hover:bg-slate-50 hover:text-slate-900 active:scale-95 transition cursor-pointer">
                    <i data-lucide="rotate-ccw" class="w-4 h-4 text-slate-500 shrink-0"></i>
                    <span>Reset</span>
                </button>

                <button type="button"
                        id="save-layout-btn"
                        onclick="saveLayout()"
                        class="inline-flex items-center gap-2 rounded-2xl border border-emerald-600 bg-emerald-600 px-5 py-2.5 text-xs font-black uppercase tracking-wider text-white shadow-md hover:bg-emerald-700 active:scale-95 transition cursor-pointer">
                    <i data-lucide="check" class="w-4 h-4 text-white shrink-0"></i>
                    <span id="save-btn-label">Save Layout</span>
                </button>
            </div>
        </div>

        <!-- Instructions Bar -->
        <div class="rounded-2xl border border-sky-100 bg-sky-50/70 p-3 sm:p-3.5 flex items-start gap-3">
            <i data-lucide="info" class="w-4 h-4 text-sky-600 shrink-0 mt-0.5"></i>
            <div class="text-xs font-medium text-sky-900">
                <span class="font-bold">Instructions:</span> Use the <span class="font-mono font-black text-slate-800 bg-white/80 px-1 py-0.5 rounded border border-slate-200">⋮⋮</span> drag handles to reorder main sections or drag categories inside and between sections. Click <span class="font-bold text-emerald-800">Save Layout</span> when done.
            </div>
        </div>
    </header>

    <!-- 2. SECTIONS & CATEGORIES SORTABLE LIST -->
    <div id="sections-list" class="space-y-4">
        @forelse($ownerHeaderSections as $section)
            <div class="section-card rounded-2xl border border-slate-200/80 bg-white p-4 sm:p-5 shadow-xs transition duration-150 select-none"
                 data-section-id="{{ $section['id'] }}">
                <!-- Section Header with Drag Handle -->
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 gap-3">
                    <div class="flex items-center gap-2.5 min-w-0 flex-1">
                        <span class="section-drag-handle cursor-grab active:cursor-grabbing font-mono text-base font-black text-slate-400 hover:text-slate-800 select-none p-1.5 rounded-lg hover:bg-slate-100 transition shrink-0" title="Drag to reorder section">
                            ⋮⋮
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="text-sm sm:text-base font-black uppercase tracking-tight text-slate-900 truncate">
                                    {{ $section['name'] }}
                                </h3>
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider {{ $section['type'] === 'income' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200/60' : 'bg-rose-50 text-rose-700 border border-rose-200/60' }}">
                                    {{ $section['type'] }}
                                </span>
                                @if(!empty($section['product_tagging_enabled']))
                                    <span class="inline-flex items-center rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200/60 px-2 py-0.5 text-[9px] font-bold">
                                        Products
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <span class="text-[11px] font-bold text-slate-400 shrink-0">
                        {{ $section['settings']->count() }} {{ \Illuminate\Support\Str::plural('item', $section['settings']->count()) }}
                    </span>
                </div>

                <!-- Category Items inside Section -->
                <div class="items-list space-y-2 mt-3 p-2 bg-slate-50/70 rounded-xl border border-slate-100 min-h-[48px]"
                     data-parent-section-id="{{ $section['id'] }}">
                    @forelse($section['settings'] as $setting)
                        <div class="item-row flex items-center justify-between gap-3 p-2.5 bg-white rounded-xl border border-slate-200/80 shadow-2xs hover:border-slate-300 transition"
                             data-setting-id="{{ $setting->id }}">
                            <div class="flex items-center gap-2.5 min-w-0 flex-1">
                                <span class="item-drag-handle cursor-grab active:cursor-grabbing font-mono text-sm font-black text-slate-400 hover:text-slate-800 select-none p-1 rounded-md hover:bg-slate-100 transition shrink-0" title="Drag to reorder item">
                                    ⋮⋮
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-xs font-bold text-slate-900 truncate">
                                            {{ $setting->displayName() }}
                                        </span>
                                        @if($setting->entryType?->code)
                                            <span class="font-mono text-[10px] text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded-sm">
                                                {{ $setting->entryType->code }}
                                            </span>
                                        @endif
                                    </div>
                                    @if($setting->companyAccount)
                                        <span class="text-[10px] text-slate-400 font-medium block truncate">
                                            {{ $setting->companyAccount->name }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="shrink-0 flex items-center gap-1.5">
                                @if($setting->is_vendor_purchase)
                                    <span class="text-[10px] font-extrabold text-amber-700 bg-amber-50 border border-amber-200/60 rounded px-1.5 py-0.5">
                                        Vendor Purchase
                                    </span>
                                @endif
                                @if($setting->is_readonly)
                                    <span class="text-[10px] font-extrabold text-slate-500 bg-slate-100 rounded px-1.5 py-0.5">
                                        Readonly
                                    </span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="py-3 text-center text-xs font-medium text-slate-400 italic empty-placeholder">
                            No items in this section. Drag items here.
                        </div>
                    @endforelse
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-200 bg-white p-8 text-center space-y-2">
                <i data-lucide="layout-grid" class="h-8 w-8 text-slate-300 mx-auto"></i>
                <h3 class="text-sm font-bold text-slate-700">No Cashbook Sections Found</h3>
                <p class="text-xs text-slate-400">This shop has no cashbook categories configured yet.</p>
            </div>
        @endforelse
    </div>

    <!-- Bottom Action Bar (Floating / Easy Save) -->
    <div class="flex items-center justify-between pt-2">
        <a href="{{ route('admin.cashbook.shop.financial-ledger', $currentShopSlugOrId) }}"
           class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-slate-800 transition">
            &larr; Back to Financial Ledger
        </a>

        <button type="button"
                onclick="saveLayout()"
                class="inline-flex items-center gap-2 rounded-2xl border border-emerald-600 bg-emerald-600 px-6 py-2.5 text-xs font-black uppercase tracking-wider text-white shadow-md hover:bg-emerald-700 active:scale-95 transition cursor-pointer">
            <i data-lucide="check" class="w-4 h-4 text-white shrink-0"></i>
            <span>Save Layout</span>
        </button>
    </div>

</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // 1. Initialize Sortable on Main Sections
        const sectionsContainer = document.getElementById('sections-list');
        if (sectionsContainer && typeof Sortable !== 'undefined') {
            Sortable.create(sectionsContainer, {
                animation: 200,
                handle: '.section-drag-handle',
                ghostClass: 'opacity-40',
                chosenClass: 'bg-indigo-50/50',
                dragClass: 'shadow-2xl',
            });
        }

        // 2. Initialize Sortable on Item Lists inside Sections
        const itemContainers = document.querySelectorAll('.items-list');
        if (typeof Sortable !== 'undefined') {
            itemContainers.forEach(container => {
                Sortable.create(container, {
                    group: 'cashbook-items',
                    animation: 200,
                    handle: '.item-drag-handle',
                    ghostClass: 'opacity-40',
                    chosenClass: 'bg-emerald-50/50',
                    dragClass: 'shadow-lg',
                    onAdd: function (evt) {
                        const placeholder = evt.to.querySelector('.empty-placeholder');
                        if (placeholder) placeholder.style.display = 'none';
                    },
                    onRemove: function (evt) {
                        const remaining = evt.from.querySelectorAll('.item-row');
                        if (remaining.length === 0) {
                            const placeholder = evt.from.querySelector('.empty-placeholder');
                            if (placeholder) placeholder.style.display = 'block';
                        }
                    }
                });
            });
        }
    });

    async function saveLayout() {
        const saveBtn = document.getElementById('save-layout-btn');
        const saveLabel = document.getElementById('save-btn-label');
        if (saveBtn && saveBtn.disabled) return;

        if (saveBtn) saveBtn.disabled = true;
        if (saveLabel) saveLabel.textContent = 'Saving...';

        const sections = [];
        document.querySelectorAll('#sections-list > [data-section-id]').forEach(secEl => {
            const sectionId = secEl.dataset.sectionId;
            const settingIds = [];
            secEl.querySelectorAll('.items-list > [data-setting-id]').forEach(itemEl => {
                settingIds.push(parseInt(itemEl.dataset.settingId));
            });
            sections.push({
                id: sectionId,
                setting_ids: settingIds,
            });
        });

        try {
            const response = await fetch('{{ route('admin.cashbook.shop.cashbook-view.save-layout', $currentShopSlugOrId) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ sections: sections })
            });

            const data = await response.json();
            if (response.ok && data.success) {
                if (typeof showToast === 'function') {
                    showToast(data.message || 'Shop Cashbook layout updated successfully.', 'success');
                } else {
                    alert(data.message || 'Shop Cashbook layout updated successfully.');
                }
            } else {
                if (typeof showToast === 'function') {
                    showToast(data.message || 'Error saving layout.', 'error');
                } else {
                    alert(data.message || 'Error saving layout.');
                }
            }
        } catch (err) {
            if (typeof showToast === 'function') {
                showToast('An unexpected error occurred: ' + err.message, 'error');
            } else {
                alert('An unexpected error occurred: ' + err.message);
            }
        } finally {
            if (saveBtn) saveBtn.disabled = false;
            if (saveLabel) saveLabel.textContent = 'Save Layout';
        }
    }

    function resetLayout() {
        if (confirm('Reset layout arrangement back to saved state?')) {
            window.location.reload();
        }
    }
</script>
@endpush
@endsection
