@extends('admin.cashbook.layouts.app')

@section('title', 'Preset Configurations & Shop Rules — Daily Ledger Engine')

@section('content')
<div class="space-y-8" x-data="presetsApp({{ json_encode($presets) }}, {{ json_encode($shops) }}, {{ json_encode($entryTypes) }})">

    {{-- BREADCRUMBS & PAGE HEADER --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-400 font-medium mb-1">
                <a href="{{ route('admin.cashbook.settings') }}" class="hover:text-slate-700 transition">Settings</a>
                <i data-lucide="chevron-right" class="w-3 h-3"></i>
                <span class="text-slate-600 font-semibold">Preset Configurations</span>
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Preset & Shop Configuration Engine</h1>
            <p class="mt-1 text-xs md:text-sm text-slate-500 max-w-3xl">
                Configure accounting entry rules (how sales, expenses, and settlements affect cash flow and balance) and assign presets to shops.
            </p>
        </div>
        <div class="flex items-center gap-3">
            <button @click="showNewPreset = true"
                    class="inline-flex items-center gap-2 px-4 py-2.5 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition shadow-md shadow-indigo-600/20">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                Create Custom Preset
            </button>
        </div>
    </div>

    {{-- STATS CARDS --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="white-card rounded-2xl p-5 border border-slate-200 shadow-sm flex items-center gap-4">
            <div class="h-12 w-12 rounded-xl bg-indigo-50 border border-indigo-100 text-indigo-600 flex items-center justify-center flex-shrink-0">
                <i data-lucide="layers" class="w-6 h-6"></i>
            </div>
            <div>
                <div class="text-2xl font-extrabold text-slate-900">{{ count($presets) }}</div>
                <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Active Presets</div>
            </div>
        </div>

        <div class="white-card rounded-2xl p-5 border border-slate-200 shadow-sm flex items-center gap-4">
            <div class="h-12 w-12 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-600 flex items-center justify-center flex-shrink-0">
                <i data-lucide="store" class="w-6 h-6"></i>
            </div>
            <div>
                <div class="text-2xl font-extrabold text-slate-900">{{ count($shops) }}</div>
                <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Shops</div>
            </div>
        </div>

        <div class="white-card rounded-2xl p-5 border border-slate-200 shadow-sm flex items-center gap-4">
            <div class="h-12 w-12 rounded-xl bg-amber-50 border border-amber-100 text-amber-600 flex items-center justify-center flex-shrink-0">
                <i data-lucide="sliders" class="w-6 h-6"></i>
            </div>
            <div>
                <div class="text-2xl font-extrabold text-slate-900">{{ count($entryTypes) }}</div>
                <div class="text-xs font-bold text-slate-500 uppercase tracking-wider">Configurable Entry Types</div>
            </div>
        </div>
    </div>

    {{-- NEW PRESET MODAL --}}
    <div x-show="showNewPreset" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4"
         @click.self="showNewPreset = false">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg p-7 border border-slate-100 animate-in fade-in zoom-in-95 duration-150">
            <div class="flex items-center justify-between mb-5 pb-4 border-b border-slate-100">
                <div>
                    <h3 class="text-lg font-extrabold text-slate-900">Create Custom Preset</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Define a customized rule set that shops can follow.</p>
                </div>
                <button @click="showNewPreset = false" class="text-slate-400 hover:text-slate-700 transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="text-xs font-extrabold text-slate-700 uppercase tracking-wide mb-1.5 block">Preset Name <span class="text-rose-500">*</span></label>
                    <input type="text" x-model="newPreset.name" placeholder="e.g. Express Outlet Preset"
                           class="w-full border border-slate-200 rounded-xl px-3.5 py-2.5 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-600 text-slate-900">
                </div>
                <div>
                    <label class="text-xs font-extrabold text-slate-700 uppercase tracking-wide mb-1.5 block">Description</label>
                    <textarea x-model="newPreset.description" rows="2"
                              placeholder="Brief description of when to assign this preset..."
                              class="w-full border border-slate-200 rounded-xl px-3.5 py-2.5 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-indigo-600 text-slate-900 resize-none"></textarea>
                </div>
                <div>
                    <label class="text-xs font-extrabold text-slate-700 uppercase tracking-wide mb-1.5 block">Initial Entry Rules Setup</label>
                    <select x-model="newPreset.copy_from_preset_id"
                            class="w-full border border-slate-200 rounded-xl px-3.5 py-2.5 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-600 text-slate-700 bg-white">
                        <option value="">Start empty (Customize all entry types one by one)</option>
                        <template x-for="p in presets" :key="p.id">
                            <option :value="p.id" x-text="'Copy rules from: ' + p.name"></option>
                        </template>
                    </select>
                </div>
                <div class="flex items-center gap-3 pt-3">
                    <button @click="createPreset()"
                            class="flex-1 py-3 text-xs font-extrabold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 transition shadow-md shadow-indigo-600/20">
                        Create Custom Preset
                    </button>
                    <button @click="showNewPreset = false"
                            class="py-3 px-5 text-xs font-extrabold text-slate-600 bg-slate-100 rounded-xl hover:bg-slate-200 transition">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- NEW ENTRY RULE OPTION MODAL --}}
    <div x-show="showNewRuleModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4"
         @click.self="showNewRuleModal = false">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg p-7 border border-slate-100 animate-in fade-in zoom-in-95 duration-150 space-y-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div>
                    <h3 class="text-lg font-extrabold text-slate-900">Add New Entry Rule Card</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Create a new entry rule option (e.g. Card, UPI, Discount) with custom accounting math.</p>
                </div>
                <button @click="showNewRuleModal = false" class="text-slate-400 hover:text-slate-700 transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div class="space-y-4 text-xs">
                <div>
                    <label class="font-extrabold text-slate-700 uppercase tracking-wide mb-1 block">Entry Rule Name <span class="text-rose-500">*</span></label>
                    <input type="text" x-model="newRule.name" placeholder="e.g. Card, UPI Payment, Customer Discount"
                           class="w-full border border-slate-200 rounded-xl px-3.5 py-2.5 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-600 text-slate-900">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="font-extrabold text-slate-700 uppercase tracking-wide mb-1 block">Entry Code (Optional)</label>
                        <input type="text" x-model="newRule.code" placeholder="e.g. CARD"
                               class="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs font-mono font-bold focus:outline-none focus:ring-2 focus:ring-indigo-600 text-slate-900 uppercase">
                    </div>
                    <div>
                        <label class="font-extrabold text-slate-700 uppercase tracking-wide mb-1 block">Category <span class="text-rose-500">*</span></label>
                        <select x-model="newRule.category"
                                class="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-indigo-600 text-slate-800 bg-white">
                            <option value="income">Sales & Income</option>
                            <option value="expense">Operating Expenses</option>
                            <option value="transfer">Settlements & Debt</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="font-extrabold text-slate-700 uppercase tracking-wide mb-1 block">Description</label>
                    <input type="text" x-model="newRule.description" placeholder="e.g. Card transaction entry rule."
                           class="w-full border border-slate-200 rounded-xl px-3.5 py-2 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-indigo-600 text-slate-900">
                </div>

                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-3">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-indigo-700 block">Accounting Math & Impact Rule Setup</span>
                    
                    <div class="grid grid-cols-3 gap-2">
                        <label class="flex items-center gap-1.5 p-2 rounded-lg border border-slate-200 bg-white cursor-pointer font-bold text-slate-700">
                            <input type="checkbox" x-model="newRule.include_in_sales" class="rounded text-indigo-600 focus:ring-indigo-500 h-4 w-4">
                            <span>In Sales</span>
                        </label>
                        <label class="flex items-center gap-1.5 p-2 rounded-lg border border-slate-200 bg-white cursor-pointer font-bold text-slate-700">
                            <input type="checkbox" x-model="newRule.include_in_expense" class="rounded text-rose-600 focus:ring-rose-500 h-4 w-4">
                            <span>In Expense</span>
                        </label>
                        <label class="flex items-center gap-1.5 p-2 rounded-lg border border-slate-200 bg-white cursor-pointer font-bold text-slate-700">
                            <input type="checkbox" x-model="newRule.include_in_pl" class="rounded text-emerald-600 focus:ring-emerald-500 h-4 w-4">
                            <span>In P&L</span>
                        </label>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <div>
                            <label class="text-[10px] font-extrabold uppercase text-slate-500 mb-1 block">Settlement Impact</label>
                            <select x-model="newRule.settlement_behavior" class="w-full border border-slate-200 rounded-lg px-2 py-1.5 font-semibold text-slate-800 bg-white">
                                <option value="none">None (0.00)</option>
                                <option value="decrease">- Decrease Settlement</option>
                                <option value="increase">+ Increase Settlement</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] font-extrabold uppercase text-slate-500 mb-1 block">Petty Impact</label>
                            <select x-model="newRule.petty_behavior" class="w-full border border-slate-200 rounded-lg px-2 py-1.5 font-semibold text-slate-800 bg-white">
                                <option value="none">None (0.00)</option>
                                <option value="decrease">- Decrease Petty</option>
                                <option value="increase">+ Increase Petty</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] font-extrabold uppercase text-slate-500 mb-1 block">Company Pending</label>
                            <select x-model="newRule.company_pending_behavior" class="w-full border border-slate-200 rounded-lg px-2 py-1.5 font-semibold text-slate-800 bg-white">
                                <option value="none">None (0.00)</option>
                                <option value="increase">+ Company Owes Shop</option>
                                <option value="decrease">- Shop Owes Company</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button @click="createEntryRule()"
                            class="flex-1 py-3 text-xs font-extrabold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 transition shadow-md shadow-indigo-600/20">
                        Create Entry Rule Card
                    </button>
                    <button @click="showNewRuleModal = false"
                            class="py-3 px-5 text-xs font-extrabold text-slate-600 bg-slate-100 rounded-xl hover:bg-slate-200 transition">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- SECTION 1 (TOP): PRESET RULE CONFIGURATOR --}}
    <div class="white-card rounded-3xl overflow-hidden border border-slate-200 shadow-sm">

        {{-- PRESET SELECTION TABS HEADER (GREY STYLING) --}}
        <div class="bg-slate-100/90 text-slate-900 p-6 md:p-7 border-b border-slate-200">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-5">
                <div>
                    <span class="text-[10px] font-extrabold uppercase tracking-widest text-indigo-600">Preset Configurator</span>
                    <h2 class="text-xl font-extrabold text-slate-900 tracking-tight mt-0.5">Select Preset to View & Tune Entry Rules</h2>
                    <p class="text-xs text-slate-500 mt-1">
                        Select a preset below to inspect each entry type's accounting rules, add/minus behavior, and cashflow effect.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.cashbook.settings.collections') }}"
                       class="px-3.5 py-2 text-xs font-bold text-cyan-700 bg-cyan-50 border border-cyan-200 rounded-xl hover:bg-cyan-100 transition flex items-center gap-1.5 shadow-xs">
                        <i data-lucide="group" class="w-3.5 h-3.5"></i>
                        Manage Collections
                    </a>
                    <button @click="showNewPreset = true"
                            class="px-3.5 py-2 text-xs font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-xl hover:bg-indigo-100 transition flex items-center gap-1.5 shadow-xs">
                        <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                        New Preset
                    </button>
                </div>
            </div>

            {{-- PRESET SWITCHER TABS --}}
            <div class="flex flex-wrap gap-2.5 pt-3 border-t border-slate-200">
                <template x-for="p in presets" :key="p.id">
                    <button @click="activePresetId = p.id"
                            :class="activePresetId == p.id ? 'bg-indigo-600 text-white border-indigo-600 shadow-md shadow-indigo-600/20' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-200/80 hover:text-slate-900 shadow-xs'"
                            class="px-4 py-2.5 rounded-xl border text-xs font-extrabold transition flex items-center gap-2">
                        <i data-lucide="layers" class="w-3.5 h-3.5"></i>
                        <span x-text="p.name"></span>
                        <span x-show="p.is_default"
                              :class="activePresetId == p.id ? 'bg-indigo-700/80 text-white border-indigo-400/40' : 'bg-emerald-50 text-emerald-700 border-emerald-200'"
                              class="text-[9px] font-black uppercase px-1.5 py-0.5 rounded border">Default</span>
                        <span :class="activePresetId == p.id ? 'bg-indigo-700/80 text-white font-mono' : 'bg-slate-100 font-mono text-slate-600 border border-slate-200'"
                              class="text-[10px] px-2 py-0.5 rounded-full"
                              x-text="p.shops ? p.shops.length + ' shops' : '0 shops'"></span>
                    </button>
                </template>
            </div>
        </div>

        {{-- ACTIVE PRESET DETAILS BAR --}}
        <template x-if="activePreset">
            <div class="bg-slate-50/90 px-6 py-4 border-b border-slate-200 flex flex-col md:flex-row md:items-center justify-between gap-3 text-xs">
                <div class="flex items-center gap-3">
                    <div class="h-9 w-9 rounded-xl bg-indigo-100 text-indigo-700 font-black flex items-center justify-center text-sm">
                        <i data-lucide="sliders-horizontal" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <div class="font-extrabold text-slate-900 text-sm" x-text="activePreset.name"></div>
                        <div class="text-slate-500 font-medium" x-text="activePreset.description || 'Configured entry type rules for cashbook balancing.'"></div>
                    </div>
                </div>
                <div class="flex items-center gap-4 text-slate-600 font-semibold">
                    <span class="bg-white px-3 py-1.5 rounded-lg border border-slate-200 shadow-xs">
                        <strong class="text-indigo-600" x-text="activePreset.entry_settings ? activePreset.entry_settings.filter(s => s.enabled).length : 0"></strong> Active Rules
                    </span>
                    <span class="bg-white px-3 py-1.5 rounded-lg border border-slate-200 shadow-xs">
                        <strong class="text-cyan-600" x-text="activePreset.collection_groups ? activePreset.collection_groups.length : 0"></strong> Collection Groups
                    </span>
                    <span class="bg-white px-3 py-1.5 rounded-lg border border-slate-200 shadow-xs">
                        <strong class="text-emerald-600" x-text="activePreset.shops ? activePreset.shops.length : 0"></strong> Assigned Shops
                    </span>
                    <button type="button" x-show="!activePreset.is_default && (!activePreset.shops || activePreset.shops.length === 0)" @click="deletePreset(activePreset.id)" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-black text-rose-700 hover:bg-rose-100">
                        Delete Preset
                    </button>
                </div>
            </div>
        </template>

        <template x-if="activePreset && activePreset.collection_groups && activePreset.collection_groups.length > 0">
            <div class="border-b border-slate-200 bg-cyan-50/60 px-6 py-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-[10px] font-black uppercase tracking-wider text-cyan-700">Collections</span>
                    <template x-for="group in activePreset.collection_groups" :key="group.id">
                        <span class="rounded-full border border-cyan-200 bg-white px-3 py-1 text-[11px] font-black text-cyan-800" x-text="group.name"></span>
                    </template>
                    <a href="{{ route('admin.cashbook.settings.collections') }}" class="rounded-full border border-cyan-200 bg-cyan-700 px-3 py-1 text-[11px] font-black text-white hover:bg-cyan-800">
                        Edit Collections
                    </a>
                </div>
            </div>
        </template>

        {{-- ENTRY RULES CONFIGURATOR CARDS --}}
        <div class="p-6 md:p-8 space-y-6">

            {{-- Category Filter Tabs & Add Rule Button --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-200 pb-4 gap-3">
                <div class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                    <i data-lucide="list-checks" class="w-4 h-4 text-indigo-600"></i>
                    Entry Type Rules Configuration
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button @click="showNewRuleModal = true"
                            class="px-3 py-1.5 rounded-lg text-xs font-extrabold text-white bg-indigo-600 hover:bg-indigo-700 transition flex items-center gap-1.5 shadow-sm">
                        <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i>
                        Add New Entry Rule Card
                    </button>
                    <div class="flex gap-1">
                        <button @click="categoryFilter = 'all'"
                                :class="categoryFilter === 'all' ? 'bg-slate-700 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                class="px-3 py-1.5 rounded-lg text-xs font-bold transition">All Rules</button>
                        <button @click="categoryFilter = 'income'"
                                :class="categoryFilter === 'income' ? 'bg-emerald-700 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                class="px-3 py-1.5 rounded-lg text-xs font-bold transition">Sales & Income</button>
                        <button @click="categoryFilter = 'expense'"
                                :class="categoryFilter === 'expense' ? 'bg-rose-700 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                class="px-3 py-1.5 rounded-lg text-xs font-bold transition">Operating Expenses</button>
                        <button @click="categoryFilter = 'transfer'"
                                :class="categoryFilter === 'transfer' ? 'bg-indigo-700 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                class="px-3 py-1.5 rounded-lg text-xs font-bold transition">Settlements & Debt</button>
                    </div>
                </div>
            </div>

            {{-- Entry Settings List Grid --}}
            <template x-if="filteredEntrySettings.length === 0">
                <div class="text-center py-12 bg-slate-50 rounded-2xl border border-dashed border-slate-200">
                    <i data-lucide="info" class="w-8 h-8 text-slate-400 mx-auto mb-2"></i>
                    <p class="text-sm font-bold text-slate-600">No entry rules match this filter.</p>
                </div>
            </template>

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
                <template x-for="setting in filteredEntrySettings" :key="setting.id">
                    <div :class="setting.enabled ? 'border-slate-200 bg-white' : 'border-slate-200/60 bg-slate-50 opacity-75'"
                         class="rounded-2xl border p-5 space-y-4 shadow-sm hover:shadow-md transition">

                        {{-- Card Header & Main Toggle --}}
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-start gap-3">
                                <div :class="getCategoryIconBg(setting)"
                                     class="h-10 w-10 rounded-xl flex items-center justify-center flex-shrink-0 font-bold text-white shadow-sm mt-0.5">
                                    <i :data-lucide="getCategoryIcon(setting)" class="w-5 h-5"></i>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h4 class="text-sm font-extrabold text-slate-900" x-text="setting.entry_type ? setting.entry_type.name : 'Entry Rule'"></h4>
                                        <span class="text-[10px] font-mono font-extrabold px-2 py-0.5 rounded bg-slate-100 text-slate-600 uppercase border border-slate-200"
                                              x-text="setting.entry_type ? setting.entry_type.code : ''"></span>
                                    </div>
                                    <p class="text-xs text-slate-500 font-medium mt-0.5" x-text="getEntryDescription(setting)"></p>
                                </div>
                            </div>

                            {{-- Enable/Disable Switch --}}
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <span class="text-[10px] font-black uppercase" :class="setting.enabled ? 'text-emerald-700' : 'text-slate-400'" x-text="setting.enabled ? 'Active' : 'Disabled'"></span>
                                <button @click="updateSettingField(setting.id, 'enabled', !setting.enabled)"
                                        :class="setting.enabled ? 'bg-emerald-500' : 'bg-slate-300'"
                                        class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none">
                                    <span :class="setting.enabled ? 'translate-x-5' : 'translate-x-0'"
                                          class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                                </button>
                            </div>
                        </div>

                        {{-- ADD FROM WHAT / MINUS FROM WHAT ACCOUNTING INFO BOX (CLEAN GREY THEME) --}}
                        <div class="rounded-xl p-3.5 bg-slate-100/90 border border-slate-200 text-slate-800 text-xs space-y-2.5 shadow-xs">
                            <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                                <span class="text-[10px] font-extrabold uppercase tracking-wider text-indigo-700 flex items-center gap-1">
                                    <i data-lucide="calculator" class="w-3.5 h-3.5"></i>
                                    Accounting Math & Impact Rule
                                </span>
                                <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 border border-indigo-200 font-bold"
                                      x-text="getMathBadgeText(setting)"></span>
                            </div>

                            {{-- Math breakdown badges --}}
                            <div class="grid grid-cols-2 gap-2 text-[11px]">
                                <div class="bg-white rounded-lg p-2.5 border border-slate-200 shadow-xs">
                                    <div class="text-[9px] font-extrabold uppercase tracking-wider text-slate-500 mb-0.5 flex items-center gap-1"><i data-lucide="plus-circle" class="w-3 h-3 text-emerald-600"></i> Adds To</div>
                                    <div class="font-extrabold text-emerald-700" x-text="getAddsToText(setting)"></div>
                                </div>
                                <div class="bg-white rounded-lg p-2.5 border border-slate-200 shadow-xs">
                                    <div class="text-[9px] font-extrabold uppercase tracking-wider text-slate-500 mb-0.5 flex items-center gap-1"><i data-lucide="minus-circle" class="w-3 h-3 text-rose-600"></i> Subtracts / Reduces</div>
                                    <div class="font-extrabold text-rose-700" x-text="getSubtractsFromText(setting)"></div>
                                </div>
                            </div>

                            {{-- Cashflow effect explanation --}}
                            <div class="bg-amber-50/80 rounded-lg p-2.5 border border-amber-200/80 text-[11px] text-amber-900 flex items-start gap-2 shadow-xs">
                                <i data-lucide="arrow-left-right" class="w-3.5 h-3.5 text-amber-600 flex-shrink-0 mt-0.5"></i>
                                <div>
                                    <strong class="text-amber-900 font-extrabold">Cashflow Effect:</strong>
                                    <span x-text="getCashflowEffectText(setting)"></span>
                                </div>
                            </div>
                        </div>

                        {{-- CONFIGURATION TOGGLES & DROPDOWNS --}}
                        <div class="space-y-3 pt-1">
                            {{-- Checkbox Flags --}}
                            <div class="grid grid-cols-3 gap-2">
                                <label class="flex items-center gap-2 p-2 rounded-lg border border-slate-200 bg-slate-50 hover:bg-white cursor-pointer transition text-xs font-bold text-slate-700">
                                    <input type="checkbox" :checked="setting.include_in_sales"
                                           @change="updateSettingField(setting.id, 'include_in_sales', $event.target.checked)"
                                           class="rounded text-indigo-600 focus:ring-indigo-500 h-4 w-4">
                                    <span>In Sales</span>
                                </label>
                                <label class="flex items-center gap-2 p-2 rounded-lg border border-slate-200 bg-slate-50 hover:bg-white cursor-pointer transition text-xs font-bold text-slate-700">
                                    <input type="checkbox" :checked="setting.include_in_expense"
                                           @change="updateSettingField(setting.id, 'include_in_expense', $event.target.checked)"
                                           class="rounded text-rose-600 focus:ring-rose-500 h-4 w-4">
                                    <span>In Expense</span>
                                </label>
                                <label class="flex items-center gap-2 p-2 rounded-lg border border-slate-200 bg-slate-50 hover:bg-white cursor-pointer transition text-xs font-bold text-slate-700">
                                    <input type="checkbox" :checked="setting.include_in_pl"
                                           @change="updateSettingField(setting.id, 'include_in_pl', $event.target.checked)"
                                           class="rounded text-emerald-600 focus:ring-emerald-500 h-4 w-4">
                                    <span>In P&L</span>
                                </label>
                            </div>

                            {{-- Behavior Dropdowns --}}
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs">
                                <div>
                                    <label class="text-[10px] font-extrabold uppercase text-slate-500 mb-1 block">Settlement Impact</label>
                                    <select :value="setting.settlement_behavior || 'none'"
                                            @change="updateSettingField(setting.id, 'settlement_behavior', $event.target.value)"
                                            class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 font-semibold text-slate-800 bg-white focus:ring-2 focus:ring-indigo-600">
                                        <option value="none">None (0.00)</option>
                                        <option value="decrease">- Decrease Settlement</option>
                                        <option value="increase">+ Increase Settlement</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-[10px] font-extrabold uppercase text-slate-500 mb-1 block">Petty Cash Impact</label>
                                    <select :value="setting.petty_behavior || 'none'"
                                            @change="updateSettingField(setting.id, 'petty_behavior', $event.target.value)"
                                            class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 font-semibold text-slate-800 bg-white focus:ring-2 focus:ring-indigo-600">
                                        <option value="none">None (0.00)</option>
                                        <option value="decrease">- Decrease Petty</option>
                                        <option value="increase">+ Increase Petty</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-[10px] font-extrabold uppercase text-slate-500 mb-1 block">Company Pending</label>
                                    <select :value="setting.company_pending_behavior || 'none'"
                                            @change="updateSettingField(setting.id, 'company_pending_behavior', $event.target.value)"
                                            class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 font-semibold text-slate-800 bg-white focus:ring-2 focus:ring-indigo-600">
                                        <option value="none">None (0.00)</option>
                                        <option value="increase">+ Company Owes Shop</option>
                                        <option value="decrease">- Shop Owes Company</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                    </div>
                </template>
            </div>

        </div>

    </div>

    {{-- SECTION 2 (BOTTOM): SHOPS TABLE & PRESET ASSIGNMENT --}}
    <div class="white-card rounded-3xl overflow-hidden border border-slate-200 shadow-sm">
        <div class="p-6 md:p-7 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-50/50">
            <div>
                <div class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-widest text-emerald-600 mb-0.5">
                    <i data-lucide="store" class="w-4 h-4"></i>
                    Shop Assignment Engine
                </div>
                <h2 class="text-xl font-extrabold text-slate-900 tracking-tight">Shops & Preset Mapping</h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    List of all shops in Green Leaf ERP. Select a preset from the dropdown to assign or update any shop's daily ledger preset.
                </p>
            </div>
            <div class="w-full md:w-72">
                <div class="relative">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-3"></i>
                    <input type="text" x-model="searchShop" placeholder="Filter shops by name or code..."
                           class="w-full border border-slate-200 rounded-xl pl-9 pr-3.5 py-2 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-600 text-slate-900 bg-white">
                </div>
            </div>
        </div>

        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse min-w-[650px]">
                <thead>
                    <tr class="bg-slate-100/70 border-b border-slate-200 text-[11px] font-extrabold uppercase tracking-wider text-slate-600">
                        <th class="py-3.5 px-6">Shop Details</th>
                        <th class="py-3.5 px-4">Owner / Client</th>
                        <th class="py-3.5 px-4">Current Active Preset</th>
                        <th class="py-3.5 px-4">Assign Preset (Select Dropdown)</th>
                        <th class="py-3.5 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs font-medium text-slate-700">
                    <template x-for="s in filteredShops" :key="s.id">
                        <tr class="hover:bg-indigo-50/30 transition">
                            {{-- Shop Name & Code --}}
                            <td class="py-4 px-6">
                                <div class="flex items-center gap-3">
                                    <div class="h-9 w-9 rounded-xl bg-slate-100 border border-slate-200 text-indigo-600 font-black flex items-center justify-center text-xs flex-shrink-0">
                                        <i data-lucide="shopping-bag" class="w-4 h-4"></i>
                                    </div>
                                    <div>
                                        <div class="font-extrabold text-slate-900 text-sm" x-text="s.name"></div>
                                        <div class="flex items-center gap-1.5 mt-0.5">
                                            <span class="font-mono text-[10px] font-bold text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded border border-slate-200" x-text="s.code"></span>
                                            <span x-show="s.enabled" class="text-[9px] font-extrabold px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 uppercase">Accounting Active</span>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            {{-- Client / Owner --}}
                            <td class="py-4 px-4">
                                <div x-show="s.client" class="flex items-center gap-1.5">
                                    <i data-lucide="user-check" class="w-3.5 h-3.5 text-slate-400"></i>
                                    <span class="font-bold text-slate-800" x-text="s.client ? s.client.name : '—'"></span>
                                </div>
                                <span x-show="!s.client" class="text-slate-400 font-semibold italic">Owned Direct</span>
                            </td>

                            {{-- Current Preset Badge --}}
                            <td class="py-4 px-4">
                                <template x-if="s.preset">
                                    <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-50 border border-indigo-200 text-indigo-900 font-extrabold text-xs shadow-xs">
                                        <i data-lucide="layers" class="w-3.5 h-3.5 text-indigo-600"></i>
                                        <span x-text="s.preset.name"></span>
                                    </div>
                                </template>
                                <template x-if="!s.preset">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 font-bold text-[11px]">
                                        <i data-lucide="alert-triangle" class="w-3 h-3"></i> No Preset Assigned
                                    </span>
                                </template>
                            </td>

                            {{-- Select Dropdown to change Preset --}}
                            <td class="py-4 px-4">
                                <select :value="s.preset_id || ''"
                                        @change="assignShopPreset(s.shop_id, $event.target.value)"
                                        class="w-full max-w-xs border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-900 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-600 shadow-sm cursor-pointer hover:border-slate-300">
                                    <option value="">— Select Preset —</option>
                                    <template x-for="p in presets" :key="p.id">
                                        <option :value="p.id"
                                                :selected="s.preset_id == p.id"
                                                x-text="p.name + (p.is_default ? ' (System Default)' : '')"></option>
                                    </template>
                                </select>
                            </td>

                            {{-- Actions --}}
                            <td class="py-4 px-6 text-right">
                                <a :href="'/admin/cashbook/shops/' + (s.slug || s.shop_id)"
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-extrabold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition">
                                    <span>Cashbook</span>
                                    <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                                </a>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function presetsApp(initialPresets, initialShops, initialEntryTypes) {
    return {
        presets: initialPresets || [],
        shops: initialShops || [],
        entryTypes: initialEntryTypes || [],
        activePresetId: (initialPresets && initialPresets.length > 0) ? initialPresets[0].id : null,
        showNewPreset: false,
        showNewRuleModal: false,
        categoryFilter: 'all',
        searchShop: '',
        newPreset: { name: '', description: '', copy_from_preset_id: '' },
        newCollection: { name: '', income_entry_type_ids: [], expense_entry_type_ids: [] },
        newRule: {
            name: '',
            code: '',
            category: 'income',
            description: '',
            include_in_sales: true,
            include_in_expense: false,
            include_in_pl: true,
            settlement_behavior: 'none',
            petty_behavior: 'none',
            company_pending_behavior: 'none'
        },

        get activePreset() {
            return this.presets.find(p => p.id == this.activePresetId) || null;
        },

        get filteredEntrySettings() {
            if (!this.activePreset || !this.activePreset.entry_settings) return [];
            let settings = this.activePreset.entry_settings;

            if (this.categoryFilter === 'income') {
                settings = settings.filter(s => s.entry_type && s.entry_type.category === 'income');
            } else if (this.categoryFilter === 'expense') {
                settings = settings.filter(s => s.entry_type && s.entry_type.category === 'expense');
            } else if (this.categoryFilter === 'transfer') {
                settings = settings.filter(s => s.entry_type && (s.entry_type.category === 'transfer' || s.entry_type.category === 'system'));
            }
            return settings;
        },

        get filteredShops() {
            if (!this.searchShop.trim()) return this.shops;
            const q = this.searchShop.toLowerCase();
            return this.shops.filter(s =>
                (s.name && s.name.toLowerCase().includes(q)) ||
                (s.code && s.code.toLowerCase().includes(q))
            );
        },

        incomeEntryTypes() {
            return this.entryTypes.filter((entry) => entry.category === 'income');
        },

        expenseEntryTypes() {
            return this.entryTypes.filter((entry) => entry.category === 'expense');
        },

        getCategoryIcon(setting) {
            if (!setting.entry_type) return 'file-text';
            const cat = setting.entry_type.category;
            if (cat === 'income') return 'trending-up';
            if (cat === 'expense') return 'trending-down';
            return 'repeat';
        },

        getCategoryIconBg(setting) {
            if (!setting.entry_type) return 'bg-slate-700';
            const cat = setting.entry_type.category;
            if (cat === 'income') return 'bg-emerald-600';
            if (cat === 'expense') return 'bg-rose-600';
            return 'bg-indigo-600';
        },

        getEntryDescription(setting) {
            if (!setting.entry_type) return '';
            const code = setting.entry_type.code;

            const descriptions = {
                'DAILY_SALES': 'Gross daily cash & counter sales collected by the shop.',
                'SALES_INCOME': 'Direct revenue entries earned from sales activities.',
                'VEHICLE_FUEL': 'Fuel and diesel expenses incurred for delivery vehicles.',
                'VEHICLE_REPAIR': 'Maintenance and repair costs for shop vehicles.',
                'STAFF_FOOD': 'Daily tea, snacks, and meal expenses for shop employees.',
                'RENT': 'Shop premises monthly rent payment.',
                'ELECTRICITY': 'Utility & electricity bill payments.',
                'CASH_HANDOVER': 'Physical cash handed over from shop to main company counter.',
                'BANK_DEPOSIT': 'Cash deposited directly into main company bank account.',
                'PURCHASER_ADVANCE': 'Cash paid out as advance to market purchaser for buying stock.',
                'STAFF_ADVANCE': 'Salary advance or loan paid out to shop staff.',
                'PETTY_TOPUP': 'Transfer of funds from sales cash to petty cash box balance.',
                'COMPANY_DEBT_PAY': 'Repayment of pending debt owed by company to shop.'
            };
            return descriptions[code] || (setting.entry_type.name + ' transaction entry rule.');
        },

        getMathBadgeText(setting) {
            if (setting.include_in_sales) return '+ SALES & SETTLEMENT';
            if (setting.include_in_expense && setting.settlement_behavior === 'decrease') return '- SUBTRACTS FROM CASH HANDOVER';
            if (setting.settlement_behavior === 'decrease') return '- DECREASES SETTLEMENT DEBT';
            if (setting.include_in_expense) return '- OPERATING EXPENSE';
            return 'INTERNAL ENTRY';
        },

        getAddsToText(setting) {
            const adds = [];
            if (setting.include_in_sales) adds.push('Gross Sales');
            if (setting.include_in_income) adds.push('Shop Revenue (P&L)');
            if (setting.settlement_behavior === 'increase') adds.push('Settlement Balance (+ Shop Debt)');
            if (setting.petty_behavior === 'increase') adds.push('Petty Cash Box (+)');
            if (setting.company_pending_behavior === 'increase') adds.push('Company Pending (+ Company Debt)');
            return adds.length > 0 ? adds.join(', ') : 'None (Neutral)';
        },

        getSubtractsFromText(setting) {
            const subs = [];
            if (setting.settlement_behavior === 'decrease') subs.push('Daily Cash Settlement (-)');
            if (setting.petty_behavior === 'decrease') subs.push('Petty Cash Box (-)');
            if (setting.company_pending_behavior === 'decrease') subs.push('Company Pending (-)');
            if (setting.include_in_expense) subs.push('Profit Calculation (Expense)');
            return subs.length > 0 ? subs.join(', ') : 'None (Neutral)';
        },

        getCashflowEffectText(setting) {
            if (setting.include_in_sales) {
                return 'Cash Inflow: Increases today\'s gross cash sales and total cash held by shop.';
            }
            if (setting.settlement_behavior === 'decrease') {
                return 'Outflow/Handover: Cash paid out to company or spent, reducing required cash handover.';
            }
            if (setting.include_in_expense) {
                return 'Expense Outflow: Cash spent from sales or petty cash, reducing daily shop net profit.';
            }
            return 'Internal Balance Adjustment: Moves money between ledger accounts without affecting external cash.';
        },

        async createPreset() {
            if (!this.newPreset.name.trim()) {
                showToast('Please enter a preset name', 'error');
                return;
            }
            try {
                const res = await fetch('/admin/cashbook/api/presets/create', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(this.newPreset),
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    this.showNewPreset = false;
                    this.presets.push(data.preset);
                    this.activePresetId = data.preset.id;
                    this.newPreset = { name: '', description: '', copy_from_preset_id: '' };
                } else {
                    showToast(data.message || 'Failed to create preset', 'error');
                }
            } catch (e) {
                showToast('Failed to create preset', 'error');
            }
        },

        async deletePreset(presetId) {
            if (!confirm('Delete this preset?')) return;

            try {
                const res = await fetch('/admin/cashbook/api/presets/delete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ preset_id: presetId }),
                });
                const data = await res.json();
                if (data.success) {
                    this.presets = this.presets.filter((preset) => preset.id !== presetId);
                    this.activePresetId = this.presets[0]?.id || null;
                    showToast(data.message || 'Preset deleted', 'success');
                } else {
                    showToast(data.message || 'Failed to delete preset', 'error');
                }
            } catch (e) {
                showToast('Failed to delete preset', 'error');
            }
        },

        async saveCollectionGroup() {
            if (!this.activePreset || !this.newCollection.name.trim() || this.newCollection.income_entry_type_ids.length === 0) {
                showToast('Collection name and income entry are required', 'error');
                return;
            }

            try {
                const res = await fetch('/admin/cashbook/api/presets/collection-group', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        preset_id: this.activePreset.id,
                        name: this.newCollection.name,
                        income_entry_type_ids: this.newCollection.income_entry_type_ids.map(Number),
                        expense_entry_type_ids: this.newCollection.expense_entry_type_ids.map(Number),
                    }),
                });
                const data = await res.json();
                if (data.success) {
                    if (!this.activePreset.collection_groups) this.activePreset.collection_groups = [];
                    const idx = this.activePreset.collection_groups.findIndex((group) => group.id === data.group.id);
                    if (idx === -1) {
                        this.activePreset.collection_groups.push(data.group);
                    } else {
                        this.activePreset.collection_groups[idx] = data.group;
                    }
                    this.newCollection = { name: '', income_entry_type_ids: [], expense_entry_type_ids: [] };
                    showToast(data.message || 'Collection group saved', 'success');
                } else {
                    showToast(data.message || 'Failed to save collection group', 'error');
                }
            } catch (e) {
                showToast('Failed to save collection group', 'error');
            }
        },

        async createEntryRule() {
            if (!this.newRule.name.trim()) {
                showToast('Please enter an entry rule name', 'error');
                return;
            }
            try {
                const res = await fetch('/admin/cashbook/api/presets/create-entry-rule', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(this.newRule),
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    this.showNewRuleModal = false;
                    if (data.created_settings && this.presets) {
                        data.created_settings.forEach(s => {
                            const p = this.presets.find(p => p.id == s.preset_id);
                            if (p) {
                                if (!p.entry_settings) p.entry_settings = [];
                                p.entry_settings.push(s);
                            }
                        });
                    }
                    this.newRule = {
                        name: '', code: '', category: 'income', description: '',
                        include_in_sales: true, include_in_expense: false, include_in_pl: true,
                        settlement_behavior: 'none', petty_behavior: 'none', company_pending_behavior: 'none'
                    };
                    this.$nextTick(() => { if (window.lucide) lucide.createIcons(); });
                } else {
                    showToast(data.message || 'Failed to create entry rule', 'error');
                }
            } catch (e) {
                showToast('Failed to create entry rule', 'error');
            }
        },

        async updateSettingField(settingId, field, value) {
            try {
                const payload = { setting_id: settingId };
                payload[field] = value;

                const res = await fetch('/admin/cashbook/api/presets/update-setting', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();
                if (data.success) {
                    // Update local reactive model
                    if (this.activePreset && this.activePreset.entry_settings) {
                        const idx = this.activePreset.entry_settings.findIndex(s => s.id == settingId);
                        if (idx !== -1) {
                            this.activePreset.entry_settings[idx][field] = value;
                        }
                    }
                    showToast('Setting rule updated', 'success');
                } else {
                    showToast(data.message || 'Failed to update rule', 'error');
                }
            } catch (e) {
                showToast('Failed to update setting', 'error');
            }
        },

        async assignShopPreset(shopId, presetId) {
            try {
                const res = await fetch('/admin/cashbook/api/assign-preset', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ shop_id: shopId, preset_id: presetId ? parseInt(presetId) : null }),
                });
                const data = await res.json();
                if (data.success) {
                    // Update local shop state
                    const shop = this.shops.find(s => s.shop_id == shopId);
                    if (shop) {
                        shop.preset_id = presetId ? parseInt(presetId) : null;
                        shop.preset = this.presets.find(p => p.id == presetId) || null;
                    }
                    showToast(data.message || 'Shop preset assigned successfully', 'success');
                } else {
                    showToast(data.message || 'Failed to assign preset', 'error');
                }
            } catch (e) {
                showToast('Failed to assign shop preset', 'error');
            }
        }
    };
}
</script>
@endpush
