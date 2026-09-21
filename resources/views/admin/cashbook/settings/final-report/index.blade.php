@extends('admin.cashbook.layouts.app')

@section('title', 'Final Report Settings - Cashbook')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.cashbook.monthly-report.overview') }}" class="text-xs font-bold text-slate-500 hover:text-emerald-600 transition flex items-center gap-1">
                    <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
                    Back to Monthly Reports
                </a>
            </div>
            <h1 class="text-2xl font-black tracking-tight text-slate-900 mt-1">Final Report Settings</h1>
            <p class="text-xs font-medium text-slate-500">Configure product filter classifications, shop ledger heading mappings, and expense category groupings for the Green Leaf monthly reports.</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-black {{ $readiness['status'] === 'ready' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : ($readiness['status'] === 'warning' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-rose-50 text-rose-700 border border-rose-200') }}">
                <span class="h-2 w-2 rounded-full {{ $readiness['status'] === 'ready' ? 'bg-emerald-500' : ($readiness['status'] === 'warning' ? 'bg-amber-500' : 'bg-rose-500') }}"></span>
                System Readiness: {{ strtoupper($readiness['status']) }}
            </span>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800 shadow-sm flex items-center gap-3">
            <i data-lucide="check-circle-2" class="h-5 w-5 text-emerald-600 shrink-0"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 shadow-sm" role="alert">
            <div class="flex items-center gap-2 text-rose-800 font-bold text-sm mb-1">
                <i data-lucide="alert-circle" class="h-4 w-4 shrink-0 text-rose-600"></i>
                <span>Please correct the errors below:</span>
            </div>
            <ul class="list-disc list-inside text-xs font-semibold text-rose-700 space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Readiness & Diagnostics Summary Card --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-4">
            <div>
                <h2 class="text-base font-black text-slate-900 flex items-center gap-2">
                    <i data-lucide="clipboard-check" class="h-5 w-5 text-emerald-600"></i>
                    Configuration Status & Diagnostics
                </h2>
                <p class="text-xs text-slate-500">Overview of configured product filters, shop ledger bindings, and expense category mappings.</p>
            </div>
            <div class="text-xs font-bold text-slate-500">
                Summary Score: <span class="font-extrabold text-slate-900">{{ count($readiness['configured_shops']) }} / {{ count($readiness['unconfigured_shops']) + count($readiness['configured_shops']) }} Shops Ready</span>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
            <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-4">
                <div class="font-black text-slate-700 mb-1 flex items-center justify-between">
                    <span>Product Filters</span>
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ empty($readiness['unmapped_product_filters']) ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                        {{ empty($readiness['unmapped_product_filters']) ? '100% Mapped' : count($readiness['unmapped_product_filters']).' Unmapped' }}
                    </span>
                </div>
                <p class="text-slate-500 text-[11px] mb-2">Used in Purchase Aggregation for Fruits, Veg, Stationery allocation.</p>
                @if(!empty($readiness['unmapped_product_filters']))
                    <div class="text-[11px] text-amber-700 font-semibold">
                        Unmapped: {{ implode(', ', $readiness['unmapped_product_filters']) }}
                    </div>
                @else
                    <div class="text-[11px] text-emerald-700 font-semibold flex items-center gap-1">
                        <i data-lucide="check" class="h-3.5 w-3.5"></i> All active filters classified
                    </div>
                @endif
            </div>

            <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-4">
                <div class="font-black text-slate-700 mb-1 flex items-center justify-between">
                    <span>Shop Headings</span>
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ empty($readiness['unconfigured_shops']) ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                        {{ count($readiness['configured_shops']) }} Active / {{ count($readiness['unconfigured_shops']) }} Pending
                    </span>
                </div>
                <p class="text-slate-500 text-[11px] mb-2">Maps client shop ledger entry types to report columns.</p>
                @if(!empty($readiness['unconfigured_shops']))
                    <div class="text-[11px] text-rose-700 font-semibold">
                        Needs Setup: {{ implode(', ', array_slice($readiness['unconfigured_shops'], 0, 3)) }}{{ count($readiness['unconfigured_shops']) > 3 ? '...' : '' }}
                    </div>
                @else
                    <div class="text-[11px] text-emerald-700 font-semibold flex items-center gap-1">
                        <i data-lucide="check" class="h-3.5 w-3.5"></i> All client shops configured
                    </div>
                @endif
            </div>

            <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-4">
                <div class="font-black text-slate-700 mb-1 flex items-center justify-between">
                    <span>Expense Mappings</span>
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ empty($readiness['unmapped_expense_categories']) ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                        {{ empty($readiness['unmapped_expense_categories']) ? '100% Mapped' : count($readiness['unmapped_expense_categories']).' Unmapped' }}
                    </span>
                </div>
                <p class="text-slate-500 text-[11px] mb-2">Maps operational expenses to standard report buckets.</p>
                @if(!empty($readiness['unmapped_expense_categories']))
                    <div class="text-[11px] text-amber-700 font-semibold">
                        Unmapped: {{ implode(', ', array_slice($readiness['unmapped_expense_categories'], 0, 3)) }}{{ count($readiness['unmapped_expense_categories']) > 3 ? '...' : '' }}
                    </div>
                @else
                    <div class="text-[11px] text-emerald-700 font-semibold flex items-center gap-1">
                        <i data-lucide="check" class="h-3.5 w-3.5"></i> All expense categories classified
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Tabbed or Sectioned Controls --}}
    <div x-data="{ activeTab: 'products' }" class="space-y-6">
        {{-- Navigation Tabs --}}
        <div class="flex items-center gap-2 border-b border-slate-200">
            <button type="button" @click="activeTab = 'products'"
                    :class="activeTab === 'products' ? 'border-emerald-600 text-emerald-700 font-black' : 'border-transparent text-slate-500 hover:text-slate-700 font-bold'"
                    class="border-b-2 px-4 py-2.5 text-xs tracking-wider uppercase transition">
                1. Purchase Product Groups
            </button>
            <button type="button" @click="activeTab = 'shops'"
                    :class="activeTab === 'shops' ? 'border-emerald-600 text-emerald-700 font-black' : 'border-transparent text-slate-500 hover:text-slate-700 font-bold'"
                    class="border-b-2 px-4 py-2.5 text-xs tracking-wider uppercase transition">
                2. Shop Report Headings
            </button>
            <button type="button" @click="activeTab = 'expenses'"
                    :class="activeTab === 'expenses' ? 'border-emerald-600 text-emerald-700 font-black' : 'border-transparent text-slate-500 hover:text-slate-700 font-bold'"
                    class="border-b-2 px-4 py-2.5 text-xs tracking-wider uppercase transition">
                3. Expense Category Mappings
            </button>
        </div>

        {{-- Tab 1: Product Groups --}}
        <div x-show="activeTab === 'products'" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-base font-extrabold text-slate-900">Purchase Product Filter Classification</h3>
                    <p class="text-xs text-slate-500">Classify purchase product filters into Fruits, Veg, Stationery, or Other for net purchase allocation.</p>
                </div>
            </div>

            <form action="{{ route('admin.cashbook.settings.final-report.product-groups') }}" method="POST" class="space-y-4">
                @csrf
                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 border-b border-slate-200 text-slate-700 font-black uppercase tracking-wider">
                            <tr>
                                <th class="px-4 py-3">Filter Name</th>
                                <th class="px-4 py-3">Assigned Category / Criteria</th>
                                <th class="px-4 py-3">Report Group Assignment</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                            @forelse($filters as $index => $filter)
                                <tr class="hover:bg-slate-50/50">
                                    <td class="px-4 py-3 font-bold text-slate-900">
                                        {{ $filter->name }}
                                        <input type="hidden" name="assignments[{{ $index }}][filter_id]" value="{{ $filter->id }}">
                                    </td>
                                    <td class="px-4 py-3 text-slate-500 font-mono text-[11px]">
                                        {{ $filter->description ?? 'All items in filter' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <select name="assignments[{{ $index }}][monthly_report_group]"
                                                class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                                {{ ! $canManage ? 'disabled' : '' }}>
                                            <option value="none" {{ (empty($filter->monthly_report_group) || $filter->monthly_report_group === 'none') ? 'selected' : '' }}>-- Unassigned / None --</option>
                                            <option value="fruits" {{ $filter->monthly_report_group === 'fruits' ? 'selected' : '' }}>Fruits</option>
                                            <option value="vegetables" {{ ($filter->monthly_report_group === 'vegetables' || $filter->monthly_report_group === 'veg') ? 'selected' : '' }}>Vegetables</option>
                                            <option value="stationery" {{ $filter->monthly_report_group === 'stationery' ? 'selected' : '' }}>Stationery</option>
                                        </select>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-6 text-center text-slate-400">No purchase product filters found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($canManage && $filters->isNotEmpty())
                    <div class="flex justify-end pt-2">
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-black text-white hover:bg-emerald-700 transition shadow-sm">
                            <i data-lucide="save" class="h-4 w-4"></i>
                            Save Product Group Assignments
                        </button>
                    </div>
                @endif
            </form>
        </div>

        {{-- Tab 2: Shop Report Headings --}}
        <div x-show="activeTab === 'shops'" x-data="{ selectedShopId: '{{ $clientShops->first()?->id ?? '' }}' }" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-base font-extrabold text-slate-900">Shop Report Heading Mappings</h3>
                    <p class="text-xs text-slate-500">Configure which ledger entry types map to Sales, Payments, Salaries, or Deductions for each shop.</p>
                </div>
            </div>

            @if($clientShops->isNotEmpty())
                {{-- Shop Selector & Bulk Copy --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-200">
                    <div>
                        <label class="block text-xs font-black uppercase tracking-wider text-slate-700 mb-1">Select Shop to Configure</label>
                        <select x-model="selectedShopId" class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                            @foreach($clientShops as $shop)
                                <option value="{{ $shop->id }}">{{ $shop->name }} ({{ $shop->client?->name ?? 'Client #'.$shop->client_id }})</option>
                            @endforeach
                        </select>
                    </div>

                    @if($canManage)
                        <div>
                            <label class="block text-xs font-black uppercase tracking-wider text-slate-700 mb-1">Bulk Copy Mappings From Another Shop</label>
                            <form action="{{ route('admin.cashbook.settings.final-report.shop-headings') }}" method="POST" class="flex gap-2">
                                @csrf
                                <input type="hidden" name="shop_id" :value="selectedShopId">
                                <select name="copy_from_shop_id" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-800">
                                    <option value="">-- Choose Source Shop --</option>
                                    @foreach($clientShops as $srcShop)
                                        <option value="{{ $srcShop->id }}">{{ $srcShop->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" onclick="return confirm('Copy all heading mappings from source shop to currently selected shop?');" class="shrink-0 rounded-xl bg-slate-800 px-4 py-2 text-xs font-black text-white hover:bg-slate-900 transition">
                                    Copy Mappings
                                </button>
                            </form>
                        </div>
                    @endif
                </div>

                {{-- Shop Specific Forms --}}
                @foreach($clientShops as $shop)
                    <div x-show="selectedShopId == '{{ $shop->id }}'" class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h4 class="text-sm font-black text-slate-900">{{ $shop->name }} — Ledger Entry Types</h4>
                            <span class="text-xs text-slate-500 font-semibold">{{ $shop->ledgerEntrySettings->count() }} Configured Entry Types</span>
                        </div>

                        <form action="{{ route('admin.cashbook.settings.final-report.shop-headings') }}" method="POST" class="space-y-4">
                            @csrf
                            <input type="hidden" name="shop_id" value="{{ $shop->id }}">

                            <div class="overflow-x-auto rounded-xl border border-slate-200">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-700 font-black uppercase tracking-wider">
                                        <tr>
                                            <th class="px-4 py-3">Entry Type Name</th>
                                            <th class="px-4 py-3">Type Nature</th>
                                            <th class="px-4 py-3">Report Heading Bucket</th>
                                            <th class="px-4 py-3">Custom Display Label (Optional)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                                        @forelse($shop->ledgerEntrySettings as $settingIndex => $setting)
                                            <tr class="hover:bg-slate-50/50">
                                                <td class="px-4 py-3 font-bold text-slate-900">
                                                    {{ $setting->entryType?->name ?? 'Type #'.$setting->entry_type_id }}
                                                    <input type="hidden" name="mappings[{{ $settingIndex }}][setting_id]" value="{{ $setting->id }}">
                                                    <input type="hidden" name="mappings[{{ $settingIndex }}][entry_type_id]" value="{{ $setting->entry_type_id }}">
                                                </td>
                                                <td class="px-4 py-3">
                                                    <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-black {{ $setting->entryType?->type === 'credit' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                                        {{ strtoupper($setting->entryType?->type ?? 'DEBIT') }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <select name="mappings[{{ $settingIndex }}][monthly_report_bucket]"
                                                            class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                                            {{ ! $canManage ? 'disabled' : '' }}>
                                                        <option value="">-- None / Default Heuristic --</option>
                                                        <optgroup label="Sales Headings">
                                                            <option value="fruits_sale" {{ $setting->monthly_report_bucket === 'fruits_sale' ? 'selected' : '' }}>Fruits Sale</option>
                                                            <option value="vegetables_sale" {{ $setting->monthly_report_bucket === 'vegetables_sale' ? 'selected' : '' }}>Vegetables Sale</option>
                                                            <option value="stationery_sale" {{ $setting->monthly_report_bucket === 'stationery_sale' ? 'selected' : '' }}>Stationery Sale</option>
                                                            <option value="other_sale" {{ $setting->monthly_report_bucket === 'other_sale' ? 'selected' : '' }}>Other / General Sale</option>
                                                        </optgroup>
                                                        <optgroup label="Product Expense Headings">
                                                            <option value="fruits_expense" {{ $setting->monthly_report_bucket === 'fruits_expense' ? 'selected' : '' }}>Fruits Purchase/Expense</option>
                                                            <option value="vegetables_expense" {{ $setting->monthly_report_bucket === 'vegetables_expense' ? 'selected' : '' }}>Vegetables Purchase/Expense</option>
                                                            <option value="stationery_expense" {{ $setting->monthly_report_bucket === 'stationery_expense' ? 'selected' : '' }}>Stationery Purchase/Expense</option>
                                                            <option value="other_product_expense" {{ $setting->monthly_report_bucket === 'other_product_expense' ? 'selected' : '' }}>Other Product Expense</option>
                                                        </optgroup>
                                                        <optgroup label="Operating Overheads">
                                                            <option value="salary" {{ $setting->monthly_report_bucket === 'salary' ? 'selected' : '' }}>Staff Salary</option>
                                                            <option value="rent" {{ $setting->monthly_report_bucket === 'rent' ? 'selected' : '' }}>Rent</option>
                                                            <option value="vehicle_fuel" {{ $setting->monthly_report_bucket === 'vehicle_fuel' ? 'selected' : '' }}>Vehicle / Fuel / Transport</option>
                                                            <option value="food_mess" {{ $setting->monthly_report_bucket === 'food_mess' ? 'selected' : '' }}>Food / Mess</option>
                                                            <option value="other_expense" {{ $setting->monthly_report_bucket === 'other_expense' ? 'selected' : '' }}>Shop Other Expense</option>
                                                        </optgroup>
                                                        <optgroup label="Exclusion">
                                                            <option value="ignore" {{ $setting->monthly_report_bucket === 'ignore' ? 'selected' : '' }}>Exclude from Final Report</option>
                                                        </optgroup>
                                                    </select>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <input type="text" name="mappings[{{ $settingIndex }}][report_heading]"
                                                           value="{{ $setting->report_heading ?? '' }}"
                                                           placeholder="e.g., Bank Transfer, Shop Exp"
                                                           class="w-full max-w-xs rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                                           {{ ! $canManage ? 'disabled' : '' }}>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="px-4 py-6 text-center text-slate-400">No ledger entry settings found for this shop.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            @if($canManage && $shop->ledgerEntrySettings->isNotEmpty())
                                <div class="flex justify-end pt-2">
                                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-black text-white hover:bg-emerald-700 transition shadow-sm">
                                        <i data-lucide="save" class="h-4 w-4"></i>
                                        Save Headings for {{ $shop->name }}
                                    </button>
                                </div>
                            @endif
                        </form>
                    </div>
                @endforeach
            @else
                <div class="p-8 text-center text-slate-400">No active client shops available to configure.</div>
            @endif
        </div>

        {{-- Tab 3: Expense Category Mappings --}}
        <div x-show="activeTab === 'expenses'" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-base font-extrabold text-slate-900">Operational Expense Category Mappings</h3>
                    <p class="text-xs text-slate-500">Map internal procurement, general other-expenses, and cashbook company categories to standard monthly report groups.</p>
                </div>
            </div>

            <form action="{{ route('admin.cashbook.settings.final-report.expense-mappings') }}" method="POST" class="space-y-6">
                @csrf
                @php
                    $mappingIdx = 0;
                    $standardReportCategories = [
                        'salary' => 'Salary & Wages',
                        'rent' => 'Rent',
                        'vehicle_fuel' => 'Vehicle & Fuel / Transport',
                        'food_mess' => 'Food & Mess',
                        'other_expense' => 'Other Operating Expenses',
                        'ignore' => 'Exclude from Final Report',
                    ];
                @endphp

                {{-- Procurement Expense Categories --}}
                <div class="space-y-3">
                    <h4 class="text-xs font-black uppercase tracking-wider text-slate-700 flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                        Procurement Expense Categories
                    </h4>
                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 border-b border-slate-200 text-slate-700 font-black uppercase tracking-wider">
                                <tr>
                                    <th class="px-4 py-2.5">Category Name / Key</th>
                                    <th class="px-4 py-2.5">Monthly Report Group</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                                @foreach($procurementCategories as $catKey => $catLabel)
                                    @php
                                        $currentMap = $expenseMappings->get('procurement_expense_category')?->firstWhere('source_key', (string) $catKey)
                                            ?? $expenseMappings->get('procurement_expense')?->firstWhere('source_key', (string) $catKey);
                                        $selectedVal = $currentMap?->report_bucket ?? $currentMap?->monthly_report_category;
                                    @endphp
                                    <tr class="hover:bg-slate-50/50">
                                        <td class="px-4 py-2.5 font-bold text-slate-900">
                                            {{ $catLabel }} <span class="text-slate-400 font-mono text-[10px]">({{ $catKey }})</span>
                                            <input type="hidden" name="mappings[{{ $mappingIdx }}][source_type]" value="procurement_expense_category">
                                            <input type="hidden" name="mappings[{{ $mappingIdx }}][source_key]" value="{{ $catKey }}">
                                        </td>
                                        <td class="px-4 py-2.5">
                                            <select name="mappings[{{ $mappingIdx }}][report_bucket]"
                                                    class="rounded-lg border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                                                    {{ ! $canManage ? 'disabled' : '' }}>
                                                <option value="">-- Default Auto-Categorization --</option>
                                                @foreach($standardReportCategories as $sKey => $sLabel)
                                                    <option value="{{ $sKey }}" {{ ($selectedVal === $sKey) ? 'selected' : '' }}>{{ $sLabel }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                    </tr>
                                    @php $mappingIdx++; @endphp
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- General Other Expenses Categories --}}
                <div class="space-y-3">
                    <h4 class="text-xs font-black uppercase tracking-wider text-slate-700 flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-blue-500"></span>
                        General Other Expense Categories
                    </h4>
                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 border-b border-slate-200 text-slate-700 font-black uppercase tracking-wider">
                                <tr>
                                    <th class="px-4 py-2.5">Category Name / Key</th>
                                    <th class="px-4 py-2.5">Monthly Report Group</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                                @foreach($otherCategories as $catKey => $catLabel)
                                    @php
                                        $currentMap = $expenseMappings->get('other_expense_category')?->firstWhere('source_key', (string) $catKey)
                                            ?? $expenseMappings->get('other_expense')?->firstWhere('source_key', (string) $catKey);
                                        $selectedVal = $currentMap?->report_bucket ?? $currentMap?->monthly_report_category;
                                    @endphp
                                    <tr class="hover:bg-slate-50/50">
                                        <td class="px-4 py-2.5 font-bold text-slate-900">
                                            {{ $catLabel }} <span class="text-slate-400 font-mono text-[10px]">({{ $catKey }})</span>
                                            <input type="hidden" name="mappings[{{ $mappingIdx }}][source_type]" value="other_expense_category">
                                            <input type="hidden" name="mappings[{{ $mappingIdx }}][source_key]" value="{{ $catKey }}">
                                        </td>
                                        <td class="px-4 py-2.5">
                                            <select name="mappings[{{ $mappingIdx }}][report_bucket]"
                                                    class="rounded-lg border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                                                    {{ ! $canManage ? 'disabled' : '' }}>
                                                <option value="">-- Default Auto-Categorization --</option>
                                                @foreach($standardReportCategories as $sKey => $sLabel)
                                                    <option value="{{ $sKey }}" {{ ($selectedVal === $sKey) ? 'selected' : '' }}>{{ $sLabel }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                    </tr>
                                    @php $mappingIdx++; @endphp
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Cashbook Company Accounting Categories --}}
                <div class="space-y-3">
                    <h4 class="text-xs font-black uppercase tracking-wider text-slate-700 flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-violet-500"></span>
                        Cashbook Company Accounting Categories
                    </h4>
                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 border-b border-slate-200 text-slate-700 font-black uppercase tracking-wider">
                                <tr>
                                    <th class="px-4 py-2.5">Category Name</th>
                                    <th class="px-4 py-2.5">Monthly Report Group</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                                @forelse($companyCategories as $cat)
                                    @php
                                        $currentMap = $expenseMappings->get('company_accounting_category')?->firstWhere('source_key', (string) $cat->id)
                                            ?? $expenseMappings->get('company_accounting_category')?->firstWhere('source_key', $cat->name);
                                        $selectedVal = $currentMap?->report_bucket ?? $currentMap?->monthly_report_category;
                                    @endphp
                                    <tr class="hover:bg-slate-50/50">
                                        <td class="px-4 py-2.5 font-bold text-slate-900">
                                            {{ $cat->name }} <span class="text-slate-400 font-mono text-[10px]">({{ $cat->type }})</span>
                                            <input type="hidden" name="mappings[{{ $mappingIdx }}][source_type]" value="company_accounting_category">
                                            <input type="hidden" name="mappings[{{ $mappingIdx }}][source_key]" value="{{ $cat->id }}">
                                        </td>
                                        <td class="px-4 py-2.5">
                                            <select name="mappings[{{ $mappingIdx }}][report_bucket]"
                                                    class="rounded-lg border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                                                    {{ ! $canManage ? 'disabled' : '' }}>
                                                <option value="">-- Default Auto-Categorization --</option>
                                                @foreach($standardReportCategories as $sKey => $sLabel)
                                                    <option value="{{ $sKey }}" {{ ($selectedVal === $sKey) ? 'selected' : '' }}>{{ $sLabel }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                    </tr>
                                    @php $mappingIdx++; @endphp
                                @empty
                                    <tr>
                                        <td colspan="2" class="px-4 py-4 text-center text-slate-400">No company accounting categories found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if($canManage)
                    <div class="flex justify-end pt-2">
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-black text-white hover:bg-emerald-700 transition shadow-sm">
                            <i data-lucide="save" class="h-4 w-4"></i>
                            Save All Expense Mappings
                        </button>
                    </div>
                @endif
            </form>
        </div>
    </div>
</div>
@endsection
