@extends('admin.cashbook.layouts.app')

@section('title', 'Shop Collection Settings - Cashbook')

@section('content')
<div class="space-y-6" x-data="collectionSettingsApp({{ json_encode($shops) }}, {{ json_encode($presets) }}, {{ json_encode($entryTypes) }})">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="mb-1 flex items-center gap-2 text-xs font-semibold text-slate-400">
                <a href="{{ route('admin.cashbook.settings') }}" class="hover:text-slate-700">Settings</a>
                <i data-lucide="chevron-right" class="h-3 w-3"></i>
                <span class="text-slate-700">Shop Collections</span>
            </div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-950">Shop Collection Settings</h1>
            <p class="mt-1 max-w-3xl text-sm font-semibold text-slate-500">
                Pick one shop, tick income rows and expense rows, then preview how the shop cashbook collection form will calculate net amount.
            </p>
        </div>
        <a href="{{ route('admin.cashbook.settings.presets') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-black text-slate-700 shadow-xs hover:bg-slate-50">
            <i data-lucide="layers" class="h-4 w-4"></i>
            Preset Rules
        </a>
    </div>

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-[22rem_1fr]">
        <section class="white-card rounded-2xl border border-slate-200 p-5 shadow-sm">
            <h2 class="text-sm font-black text-slate-950">1. Select Shop</h2>
            <p class="mt-1 text-xs font-semibold text-slate-500">Collection setup is saved to the shop's assigned preset.</p>

            <input type="search" x-model="shopSearch" placeholder="Search shop..." class="mt-4 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-950 focus:border-cyan-400 focus:outline-none">

            <div class="mt-3 max-h-[34rem] space-y-2 overflow-y-auto pr-1">
                <template x-for="shop in filteredShops()" :key="shop.shop_id">
                    <button type="button" @click="selectShop(shop.shop_id)" class="w-full rounded-xl border p-3 text-left transition" :class="activeShopId === shop.shop_id ? 'border-cyan-300 bg-cyan-50 shadow-sm' : 'border-slate-200 bg-white hover:bg-slate-50'">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-black text-slate-950" x-text="shop.name"></p>
                                <p class="mt-0.5 text-[11px] font-semibold text-slate-500" x-text="shop.code || ('Shop #' + shop.shop_id)"></p>
                            </div>
                            <span class="rounded-full bg-white px-2 py-0.5 text-[10px] font-black text-cyan-800" x-text="shop.preset ? shop.preset.name : 'No preset'"></span>
                        </div>
                    </button>
                </template>
            </div>
        </section>

        <section class="space-y-5">
            <div class="white-card rounded-2xl border border-slate-200 p-5 shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-100 pb-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-sm font-black text-slate-950">2. Collection Form Setup</h2>
                        <p class="mt-1 text-xs font-semibold text-slate-500">
                            This is the form shop incharge will see. Tick rows here; dummy amounts only preview the calculation.
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-black text-slate-700" x-text="activeShop ? activeShop.name : 'No shop'"></span>
                        <span class="rounded-full border border-cyan-200 bg-cyan-50 px-3 py-1 text-[11px] font-black text-cyan-800" x-text="activePreset ? activePreset.name : 'Assign preset first'"></span>
                    </div>
                </div>

                <div x-show="!activePreset" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm font-bold text-amber-800">
                    This shop has no preset assigned. Assign a preset first, then create collection rows.
                </div>

                <div x-show="activePreset" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500">Collection Name</label>
                        <input type="text" x-model="form.name" placeholder="Collection" class="mt-1 h-10 w-full max-w-md rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-950 focus:border-cyan-400 focus:outline-none">
                    </div>

                    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-4">
                            <div class="mb-3 flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <i data-lucide="plus-circle" class="h-4 w-4 text-emerald-700"></i>
                                    <h3 class="text-xs font-black uppercase tracking-wider text-emerald-800">Income Rows</h3>
                                </div>
                                <span class="rounded-full bg-white px-2 py-0.5 text-[10px] font-black text-emerald-700" x-text="form.income_entry_type_ids.length + ' selected'"></span>
                            </div>

                            <div class="space-y-2">
                                <template x-for="entry in incomeEntryTypes()" :key="entry.id">
                                    <label class="grid cursor-pointer grid-cols-[1.5rem_1fr_8rem] items-center gap-2 rounded-lg border bg-white px-2.5 py-2 text-xs transition" :class="isIncomeSelected(entry.id) ? 'border-emerald-300 text-emerald-800 shadow-sm' : 'border-white text-slate-700 hover:border-emerald-200'">
                                        <input type="checkbox" :value="entry.id" x-model="form.income_entry_type_ids" class="rounded border-emerald-300 text-emerald-600">
                                        <span class="font-black" x-text="entry.name"></span>
                                        <input type="number" min="0" step="0.01" x-model="demoAmounts[entry.id]" @click.stop class="h-8 rounded-lg border border-slate-200 bg-slate-50 px-2 text-right text-xs font-black text-slate-950" :disabled="!isIncomeSelected(entry.id)">
                                    </label>
                                </template>
                            </div>
                        </div>

                        <div class="rounded-xl border border-rose-200 bg-rose-50/70 p-4">
                            <div class="mb-3 flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <i data-lucide="minus-circle" class="h-4 w-4 text-rose-700"></i>
                                    <h3 class="text-xs font-black uppercase tracking-wider text-rose-800">Expense Rows</h3>
                                </div>
                                <span class="rounded-full bg-white px-2 py-0.5 text-[10px] font-black text-rose-700" x-text="form.expense_entry_type_ids.length + ' selected'"></span>
                            </div>

                            <div class="space-y-2">
                                <template x-for="entry in expenseEntryTypes()" :key="entry.id">
                                    <label class="grid cursor-pointer grid-cols-[1.5rem_1fr_8rem] items-center gap-2 rounded-lg border bg-white px-2.5 py-2 text-xs transition" :class="isExpenseSelected(entry.id) ? 'border-rose-300 text-rose-800 shadow-sm' : 'border-white text-slate-700 hover:border-rose-200'">
                                        <input type="checkbox" :value="entry.id" x-model="form.expense_entry_type_ids" class="rounded border-rose-300 text-rose-600">
                                        <span class="font-black" x-text="entry.name"></span>
                                        <input type="number" min="0" step="0.01" x-model="demoAmounts[entry.id]" @click.stop class="h-8 rounded-lg border border-slate-200 bg-slate-50 px-2 text-right text-xs font-black text-slate-950" :disabled="!isExpenseSelected(entry.id)">
                                    </label>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-cyan-200 bg-cyan-50 p-4">
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="text-xs font-black uppercase tracking-wider text-cyan-800">Live Demo Preview</h3>
                            <strong class="text-lg font-black text-cyan-950" x-text="money(netDemo())"></strong>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="text-[10px] font-black uppercase text-cyan-800">
                                    <tr>
                                        <th class="py-2 pr-3">Row</th>
                                        <th class="py-2 pr-3">Type</th>
                                        <th class="py-2 text-right">Demo Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-cyan-100">
                                    <template x-for="row in demoRows()" :key="row.id + '-' + row.role">
                                        <tr>
                                            <td class="py-2 pr-3 font-black text-slate-900" x-text="row.name"></td>
                                            <td class="py-2 pr-3 font-bold" :class="row.role === 'income' ? 'text-emerald-700' : 'text-rose-700'" x-text="row.role"></td>
                                            <td class="py-2 text-right font-mono font-black" :class="row.role === 'income' ? 'text-emerald-700' : 'text-rose-700'" x-text="(row.role === 'income' ? '+' : '-') + money(row.amount)"></td>
                                        </tr>
                                    </template>
                                    <tr class="border-t border-cyan-200">
                                        <td colspan="2" class="py-2 pr-3 text-right text-[10px] font-black uppercase text-cyan-800">Net Collection</td>
                                        <td class="py-2 text-right font-mono text-sm font-black text-cyan-950" x-text="money(netDemo())"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="button" @click="saveCollectionGroup()" class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-cyan-700 px-5 text-xs font-black text-white hover:bg-cyan-800">
                            <i data-lucide="save" class="h-4 w-4"></i>
                            Save for This Shop Preset
                        </button>
                    </div>
                </div>
            </div>

            <div class="white-card rounded-2xl border border-slate-200 p-5 shadow-sm">
                <h2 class="text-sm font-black text-slate-950">Existing Collections for This Shop</h2>
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
                        No collection groups for this shop's preset.
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
function collectionSettingsApp(initialShops, initialPresets, initialEntryTypes) {
    return {
        shops: initialShops || [],
        presets: initialPresets || [],
        entryTypes: initialEntryTypes || [],
        activeShopId: initialShops && initialShops.length > 0 ? initialShops[0].shop_id : null,
        shopSearch: '',
        form: {
            name: 'Collection',
            income_entry_type_ids: [],
            expense_entry_type_ids: [],
        },
        demoAmounts: {},

        get activeShop() {
            return this.shops.find((shop) => Number(shop.shop_id) === Number(this.activeShopId)) || null;
        },

        get activePreset() {
            if (!this.activeShop || !this.activeShop.preset_id) return null;
            return this.presets.find((preset) => Number(preset.id) === Number(this.activeShop.preset_id)) || this.activeShop.preset || null;
        },

        filteredShops() {
            const q = this.shopSearch.trim().toLowerCase();
            if (!q) return this.shops;
            return this.shops.filter((shop) =>
                String(shop.name || '').toLowerCase().includes(q) ||
                String(shop.code || '').toLowerCase().includes(q)
            );
        },

        selectShop(shopId) {
            this.activeShopId = Number(shopId);
            this.form = {
                name: 'Collection',
                income_entry_type_ids: [],
                expense_entry_type_ids: [],
            };
            this.demoAmounts = {};
            this.applyDemoDefaults();
        },

        incomeEntryTypes() {
            return this.entryTypes.filter((entry) => entry.category === 'income');
        },

        expenseEntryTypes() {
            return this.entryTypes.filter((entry) => entry.category === 'expense');
        },

        isIncomeSelected(id) {
            return this.form.income_entry_type_ids.map(Number).includes(Number(id));
        },

        isExpenseSelected(id) {
            return this.form.expense_entry_type_ids.map(Number).includes(Number(id));
        },

        activeCollectionGroups() {
            return this.activePreset?.collection_groups || [];
        },

        applyDemoDefaults() {
            const smIncome = this.incomeEntryTypes().find((entry) => String(entry.code).includes('s_m_delivery'));
            const rentExpense = this.expenseEntryTypes().find((entry) => String(entry.code).includes('rent'));
            const shopDeduct = this.expenseEntryTypes().find((entry) => String(entry.code).includes('shop_deduct'));

            if (smIncome) {
                this.form.income_entry_type_ids = [smIncome.id];
                this.demoAmounts[smIncome.id] = this.demoAmounts[smIncome.id] || 11000;
            }
            if (rentExpense) {
                this.form.expense_entry_type_ids.push(rentExpense.id);
                this.demoAmounts[rentExpense.id] = this.demoAmounts[rentExpense.id] || 5000;
            }
            if (shopDeduct) {
                this.form.expense_entry_type_ids.push(shopDeduct.id);
                this.demoAmounts[shopDeduct.id] = this.demoAmounts[shopDeduct.id] || 1000;
            }
        },

        demoRows() {
            const incomeRows = this.incomeEntryTypes()
                .filter((entry) => this.isIncomeSelected(entry.id))
                .map((entry) => ({ id: entry.id, name: entry.name, role: 'income', amount: Number(this.demoAmounts[entry.id] || 0) }));
            const expenseRows = this.expenseEntryTypes()
                .filter((entry) => this.isExpenseSelected(entry.id))
                .map((entry) => ({ id: entry.id, name: entry.name, role: 'expense', amount: Number(this.demoAmounts[entry.id] || 0) }));
            return [...incomeRows, ...expenseRows];
        },

        netDemo() {
            return this.demoRows().reduce((sum, row) => row.role === 'income' ? sum + row.amount : sum - row.amount, 0);
        },

        money(value) {
            return 'Rs. ' + Number(value || 0).toFixed(2);
        },

        async saveCollectionGroup() {
            if (!this.activeShop || !this.activePreset) {
                showToast('Select a shop with an assigned preset first.', 'error');
                return;
            }
            if (!this.form.name.trim() || this.form.income_entry_type_ids.length === 0) {
                showToast('Collection name and at least one income row are required.', 'error');
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

                if (!this.activePreset.collection_groups) this.activePreset.collection_groups = [];
                const idx = this.activePreset.collection_groups.findIndex((group) => group.id === payload.group.id);
                if (idx === -1) {
                    this.activePreset.collection_groups.push(payload.group);
                } else {
                    this.activePreset.collection_groups[idx] = payload.group;
                }
                showToast(payload.message || 'Collection group saved.', 'success');
                this.$nextTick(() => window.lucide && lucide.createIcons());
            } catch (error) {
                showToast('Unable to save collection group.', 'error');
            }
        },

        init() {
            this.applyDemoDefaults();
        },
    };
}
</script>
@endpush
