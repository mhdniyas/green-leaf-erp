@extends('admin.cashbook.layouts.app')

@section('title', 'Collection Groups - Cashbook Settings')

@section('content')
<div class="space-y-6" x-data="collectionGroupsApp({{ json_encode($presets) }}, {{ json_encode($entryTypes) }})">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="mb-1 flex items-center gap-2 text-xs font-semibold text-slate-400">
                <a href="{{ route('admin.cashbook.settings') }}" class="hover:text-slate-700">Settings</a>
                <i data-lucide="chevron-right" class="h-3 w-3"></i>
                <a href="{{ route('admin.cashbook.settings.presets') }}" class="hover:text-slate-700">Presets</a>
                <i data-lucide="chevron-right" class="h-3 w-3"></i>
                <span class="text-slate-700">Collections</span>
            </div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-950">Collection Groups</h1>
            <p class="mt-1 max-w-3xl text-sm font-semibold text-slate-500">
                Configure grouped collection forms per preset. Shops assigned to that preset will see the collection option in shop cashbook.
            </p>
        </div>
        <a href="{{ route('admin.cashbook.settings.presets') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-black text-slate-700 shadow-xs hover:bg-slate-50">
            <i data-lucide="layers" class="h-4 w-4"></i>
            Back to Presets
        </a>
    </div>

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-[24rem_1fr]">
        <section class="white-card rounded-2xl border border-slate-200 p-5 shadow-sm">
            <h2 class="text-sm font-black text-slate-950">1. Choose Preset</h2>
            <p class="mt-1 text-xs font-semibold text-slate-500">Collection groups are available only to shops using the selected preset.</p>

            <div class="mt-4 space-y-2">
                <template x-for="preset in presets" :key="preset.id">
                    <button type="button" @click="selectPreset(preset.id)" class="w-full rounded-xl border p-3 text-left transition" :class="activePresetId === preset.id ? 'border-cyan-300 bg-cyan-50 shadow-sm' : 'border-slate-200 bg-white hover:bg-slate-50'">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-black text-slate-950" x-text="preset.name"></p>
                                <p class="mt-0.5 text-[11px] font-semibold text-slate-500" x-text="(preset.shops ? preset.shops.length : 0) + ' assigned shops'"></p>
                            </div>
                            <span class="rounded-full bg-white px-2 py-0.5 text-[10px] font-black text-cyan-800" x-text="(preset.collection_groups ? preset.collection_groups.length : 0) + ' groups'"></span>
                        </div>
                    </button>
                </template>
            </div>
        </section>

        <section class="space-y-5">
            <div class="white-card rounded-2xl border border-slate-200 p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4 border-b border-slate-100 pb-4">
                    <div>
                        <h2 class="text-sm font-black text-slate-950">2. Create Collection</h2>
                        <p class="mt-1 text-xs font-semibold text-slate-500">Example: S/M Delivery income minus Rent and Shop Deduction.</p>
                    </div>
                    <span class="rounded-full border border-cyan-200 bg-cyan-50 px-3 py-1 text-[11px] font-black text-cyan-800" x-text="activePreset ? activePreset.name : 'No preset'"></span>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Collection Name</label>
                        <input type="text" x-model="form.name" placeholder="S/M Delivery Collection" class="mt-1 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-950 focus:border-cyan-400 focus:outline-none">
                    </div>

                    <div class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-3">
                        <div class="mb-2 flex items-center gap-2">
                            <i data-lucide="plus-circle" class="h-4 w-4 text-emerald-700"></i>
                            <label class="text-[10px] font-black uppercase tracking-wider text-emerald-800">Income Rows</label>
                        </div>
                        <div class="max-h-52 space-y-1 overflow-y-auto pr-1">
                            <template x-for="entry in incomeEntryTypes()" :key="entry.id">
                                <label class="flex cursor-pointer items-center gap-2 rounded-lg bg-white px-2.5 py-2 text-xs font-bold text-slate-800 hover:bg-emerald-50">
                                    <input type="checkbox" :value="entry.id" x-model="form.income_entry_type_ids" class="rounded border-emerald-300 text-emerald-600">
                                    <span x-text="entry.name"></span>
                                </label>
                            </template>
                        </div>
                    </div>

                    <div class="rounded-xl border border-rose-200 bg-rose-50/70 p-3">
                        <div class="mb-2 flex items-center gap-2">
                            <i data-lucide="minus-circle" class="h-4 w-4 text-rose-700"></i>
                            <label class="text-[10px] font-black uppercase tracking-wider text-rose-800">Debit / Expense Rows</label>
                        </div>
                        <div class="max-h-52 space-y-1 overflow-y-auto pr-1">
                            <template x-for="entry in expenseEntryTypes()" :key="entry.id">
                                <label class="flex cursor-pointer items-center gap-2 rounded-lg bg-white px-2.5 py-2 text-xs font-bold text-slate-800 hover:bg-rose-50">
                                    <input type="checkbox" :value="entry.id" x-model="form.expense_entry_type_ids" class="rounded border-rose-300 text-rose-600">
                                    <span x-text="entry.name"></span>
                                </label>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="text-xs font-semibold text-slate-600">
                        Shop form will show selected income fields first, then selected debit fields, and calculate net collection live.
                    </div>
                    <button type="button" @click="saveCollectionGroup()" class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-cyan-700 px-4 text-xs font-black text-white hover:bg-cyan-800">
                        <i data-lucide="save" class="h-4 w-4"></i>
                        Save Collection Group
                    </button>
                </div>
            </div>

            <div class="white-card rounded-2xl border border-slate-200 p-5 shadow-sm">
                <h2 class="text-sm font-black text-slate-950">Existing Collections</h2>
                <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-2">
                    <template x-for="group in activeCollectionGroups()" :key="group.id">
                        <div class="rounded-xl border border-cyan-100 bg-cyan-50/50 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-black text-slate-950" x-text="group.name"></p>
                                    <p class="mt-0.5 text-[11px] font-semibold text-slate-500" x-text="group.code"></p>
                                </div>
                                <span class="rounded-full bg-white px-2 py-0.5 text-[10px] font-black text-cyan-800" x-text="(group.entry_types || []).length + ' rows'"></span>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-1.5">
                                <template x-for="line in (group.entry_types || [])" :key="line.id">
                                    <span class="rounded-full border px-2 py-0.5 text-[10px] font-black" :class="line.role === 'income' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700'" x-text="line.entry_type ? line.entry_type.name : line.entry_type_id"></span>
                                </template>
                            </div>
                        </div>
                    </template>
                    <div x-show="activeCollectionGroups().length === 0" class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-xs font-bold text-slate-400">
                        No collection groups for this preset.
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
function collectionGroupsApp(initialPresets, initialEntryTypes) {
    return {
        presets: initialPresets || [],
        entryTypes: initialEntryTypes || [],
        activePresetId: initialPresets && initialPresets.length > 0 ? initialPresets[0].id : null,
        form: {
            name: '',
            income_entry_type_ids: [],
            expense_entry_type_ids: [],
        },

        get activePreset() {
            return this.presets.find((preset) => Number(preset.id) === Number(this.activePresetId)) || null;
        },

        selectPreset(presetId) {
            this.activePresetId = Number(presetId);
            this.resetForm();
        },

        incomeEntryTypes() {
            return this.entryTypes.filter((entry) => entry.category === 'income');
        },

        expenseEntryTypes() {
            return this.entryTypes.filter((entry) => entry.category === 'expense');
        },

        activeCollectionGroups() {
            return this.activePreset?.collection_groups || [];
        },

        resetForm() {
            this.form = {
                name: '',
                income_entry_type_ids: [],
                expense_entry_type_ids: [],
            };
        },

        async saveCollectionGroup() {
            if (!this.activePreset || !this.form.name.trim() || this.form.income_entry_type_ids.length === 0) {
                showToast('Choose a preset, name, and at least one income row.', 'error');
                return;
            }

            try {
                const response = await fetch('/admin/cashbook/api/presets/collection-group', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        preset_id: this.activePreset.id,
                        name: this.form.name,
                        income_entry_type_ids: this.form.income_entry_type_ids.map(Number),
                        expense_entry_type_ids: this.form.expense_entry_type_ids.map(Number),
                    }),
                });
                const payload = await response.json();
                if (!payload.success) {
                    showToast(payload.message || 'Unable to save collection group.', 'error');
                    return;
                }

                if (!this.activePreset.collection_groups) {
                    this.activePreset.collection_groups = [];
                }
                const idx = this.activePreset.collection_groups.findIndex((group) => group.id === payload.group.id);
                if (idx === -1) {
                    this.activePreset.collection_groups.push(payload.group);
                } else {
                    this.activePreset.collection_groups[idx] = payload.group;
                }
                this.resetForm();
                showToast(payload.message || 'Collection group saved.', 'success');
                this.$nextTick(() => window.lucide && lucide.createIcons());
            } catch (error) {
                showToast('Unable to save collection group.', 'error');
            }
        },
    };
}
</script>
@endpush
