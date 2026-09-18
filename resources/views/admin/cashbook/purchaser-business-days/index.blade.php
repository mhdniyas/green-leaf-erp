@extends('admin.cashbook.layouts.app')

@section('title', 'Purchaser Business Days — Admin Oversight')
@section('page_title', 'Purchaser Business Days Oversight')
@section('page_description', 'Executive cashbook oversight, daily matching verification, and business day lifecycle reports.')

@section('content')
<div class="space-y-6" x-data="productAllotmentApp({{ json_encode($allProducts) }}, {{ json_encode($categories) }})">
    @if(session('success'))
        <div class="flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3.5 text-xs font-bold text-emerald-800 shadow-xs">
            <i data-lucide="check-circle" class="h-4 w-4 text-emerald-600 shrink-0"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs font-bold text-rose-800 shadow-xs">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Header Controls & Navigation -->
    <div class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-black uppercase tracking-[0.2em] text-slate-400">Cashbook Oversight</span>
                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-emerald-700 border border-emerald-200">
                        Admin View Only
                    </span>
                </div>
                <h2 class="mt-1 text-2xl font-black text-slate-950">
                    Purchaser Business Days — {{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}
                </h2>
                <p class="mt-0.5 text-xs font-semibold text-slate-500">
                    Monitoring and audit control for all warehouse business days. Read-only canonical synchronization.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <button type="button" @click="allotmentSectionOpen = !allotmentSectionOpen" class="inline-flex h-11 items-center justify-center rounded-2xl bg-emerald-700 px-5 text-xs font-black text-white shadow-sm transition hover:bg-emerald-800">
                    <i data-lucide="user-check" class="w-4 h-4 mr-2"></i>
                    Product Allotment
                </button>
                <a href="{{ route('admin.cashbook.purchaser-business-days.reports', ['month' => $month]) }}" class="inline-flex h-11 items-center justify-center rounded-2xl bg-teal-600 px-5 text-xs font-black text-white shadow-sm transition hover:bg-teal-500">
                    <i data-lucide="file-spreadsheet" class="w-4 h-4 mr-2"></i>
                    Business Day Reports
                </a>
            </div>
        </div>
    </div>

    <!-- Product Allotment Section -->
    <div x-show="allotmentSectionOpen" x-transition class="rounded-[2rem] border border-emerald-200 bg-emerald-50/30 p-6 shadow-sm space-y-6">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-emerald-100 pb-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-emerald-600 text-white text-xs font-black">
                        <i data-lucide="user-plus" class="w-3.5 h-3.5"></i>
                    </span>
                    <h3 class="text-lg font-black text-slate-950">Product → Purchaser Allotment</h3>
                </div>
                <p class="mt-1 text-xs font-semibold text-slate-600">
                    Assign purchaser historical responsibility for Business Day reconciliation. Category selection is a multi-select helper tool; saved allotments remain product-level.
                </p>
            </div>
            <button type="button" @click="allotmentSectionOpen = false" class="text-xs font-bold text-slate-400 hover:text-slate-600">
                Close Section ✕
            </button>
        </div>

        <!-- Assignment Form Controls -->
        <div class="grid gap-5 lg:grid-cols-12 bg-white rounded-2xl border border-slate-200 p-5 shadow-xs">
            <!-- Left Controls: Purchaser, Date, Categories -->
            <div class="lg:col-span-5 space-y-4">
                <!-- Purchaser Selector -->
                <div>
                    <label class="block text-xs font-black uppercase text-slate-600 mb-1.5">1. Purchaser</label>
                    <select x-model="purchaser_user_id" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none">
                        <option value="">Select Purchaser...</option>
                        @foreach($purchasers as $purchaser)
                            <option value="{{ $purchaser->id }}">{{ $purchaser->name }} ({{ $purchaser->email }})</option>
                        @endforeach
                    </select>
                </div>

                <!-- Effective From Date -->
                <div>
                    <label class="block text-xs font-black uppercase text-slate-600 mb-1.5">2. Effective From Date</label>
                    <input type="date" x-model="effective_from" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none">
                    <p class="mt-1 text-[10px] font-semibold text-slate-500">
                        Historical active allotments are closed on the day before this date. Future history remains intact.
                    </p>
                </div>

                <!-- Multi-Category Picker -->
                <div class="relative" @click.away="categoryDropdownOpen = false">
                    <label class="block text-xs font-black uppercase text-slate-600 mb-1.5">
                        3. Categories (Multi-Select Helper)
                    </label>
                    <button type="button" @click="categoryDropdownOpen = !categoryDropdownOpen" class="w-full flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-xs font-bold text-slate-900 focus:border-emerald-500 focus:bg-white focus:outline-none">
                        <span x-text="selectedCategories.length === 0 ? 'Select Categories ▼' : selectedCategories.length + ' Categories Selected'"></span>
                        <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400"></i>
                    </button>

                    <!-- Dropdown Content -->
                    <div x-show="categoryDropdownOpen" x-cloak class="absolute left-0 right-0 z-30 mt-2 rounded-xl border border-slate-200 bg-white p-3 shadow-xl space-y-2 max-h-60 overflow-y-auto">
                        <input type="text" x-model="categorySearch" placeholder="Search categories..." class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-800 placeholder-slate-400">
                        <div class="space-y-1">
                            <template x-for="cat in filteredCategories" :key="cat.id">
                                <label class="flex items-center gap-2 rounded-lg p-1.5 hover:bg-slate-50 text-xs font-bold text-slate-800 cursor-pointer">
                                    <input type="checkbox" :checked="isCategorySelected(cat.id)" @change="toggleCategory(cat.id)" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                    <span x-text="cat.name"></span>
                                </label>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Form Action Button -->
                <div class="pt-2">
                    <button type="button" @click="openPreview()" :disabled="isLoadingPreview || selectedProducts.length === 0 || !purchaser_user_id" class="w-full flex items-center justify-center gap-2 rounded-xl bg-emerald-700 px-5 py-3 text-xs font-black text-white shadow-sm transition hover:bg-emerald-800 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span x-show="!isLoadingPreview">Preview & Assign (<span x-text="selectedProducts.length"></span>)</span>
                        <span x-show="isLoadingPreview">Loading Preview...</span>
                    </button>
                </div>
            </div>

            <!-- Right Controls: Product Selection Grid -->
            <div class="lg:col-span-7 space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-slate-100 pb-2">
                    <div>
                        <span class="text-xs font-black uppercase text-slate-700">Selected Products: </span>
                        <span class="text-sm font-black text-emerald-700" x-text="selectedProducts.length"></span>
                        <span class="text-xs font-semibold text-slate-400" x-text="'/ ' + allProducts.length"></span>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button" @click="selectAllVisibleProducts()" class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-bold text-slate-700 hover:bg-slate-100">
                            Select All
                        </button>
                        <button type="button" @click="clearAllProducts()" class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-bold text-rose-600 hover:bg-rose-50">
                            Clear All
                        </button>
                    </div>
                </div>

                <!-- Product Search -->
                <input type="text" x-model="productSearch" placeholder="Search products by name or SKU..." class="w-full rounded-xl border border-slate-200 px-3.5 py-2 text-xs font-semibold text-slate-800 placeholder-slate-400 focus:border-emerald-500 focus:outline-none">

                <!-- Product List Checklist -->
                <div class="max-h-72 overflow-y-auto rounded-xl border border-slate-200 divide-y divide-slate-100 bg-slate-50/50 p-2 space-y-1">
                    <template x-for="prod in filteredProducts" :key="prod.id">
                        <label class="flex items-center justify-between gap-3 rounded-lg bg-white p-2.5 hover:bg-slate-50 cursor-pointer shadow-2xs">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <input type="checkbox" :checked="isProductSelected(prod.id)" @change="toggleProduct(prod.id)" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 shrink-0">
                                <div class="min-w-0">
                                    <span class="text-xs font-bold text-slate-900 truncate block" x-text="prod.name"></span>
                                    <span class="text-[10px] font-mono text-slate-400" x-text="prod.sku ? 'SKU: ' + prod.sku : ''"></span>
                                </div>
                            </div>
                            <div class="shrink-0 text-right">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold"
                                      :class="prod.current_purchaser_name ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-amber-50 text-amber-800 border border-amber-200'"
                                      x-text="'Current: ' + (prod.current_purchaser_name || 'Unassigned')">
                                </span>
                            </div>
                        </label>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Confirmation Modal -->
    <div x-show="showPreviewModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs">
        <div @click.away="showPreviewModal = false" class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-base font-black text-slate-950">Confirm Allotment Assignment</h3>
                <button type="button" @click="showPreviewModal = false" class="text-slate-400 hover:text-slate-600">
                    ✕
                </button>
            </div>

            <form method="POST" action="{{ route('admin.cashbook.purchaser-business-days.allotments.assign') }}" class="space-y-4">
                @csrf
                <template x-for="id in selectedProducts" :key="id">
                    <input type="hidden" name="product_ids[]" :value="id">
                </template>
                <input type="hidden" name="purchaser_user_id" :value="purchaser_user_id">
                <input type="hidden" name="effective_from" :value="effective_from">

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-2 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-slate-500">Purchaser:</span>
                        <span class="font-black text-slate-900" x-text="previewData?.purchaser_name"></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-slate-500">Effective From:</span>
                        <span class="font-black text-slate-900" x-text="previewData?.effective_from"></span>
                    </div>
                    <div class="flex items-center justify-between border-t border-slate-200 pt-2 font-black">
                        <span class="text-slate-700">Total Products:</span>
                        <span class="text-emerald-700 text-sm" x-text="previewData?.total_selected"></span>
                    </div>
                </div>

                <!-- Preview Breakdown -->
                <div class="space-y-2 text-xs font-semibold">
                    <div class="flex items-center justify-between rounded-xl bg-emerald-50 px-3.5 py-2.5 text-emerald-800 border border-emerald-200">
                        <span>Products already assigned to purchaser:</span>
                        <span class="font-black text-sm" x-text="previewData?.already_assigned_count"></span>
                    </div>
                    <div class="flex items-center justify-between rounded-xl bg-amber-50 px-3.5 py-2.5 text-amber-800 border border-amber-200">
                        <span>Products changing purchaser:</span>
                        <span class="font-black text-sm" x-text="previewData?.changing_purchaser_count"></span>
                    </div>
                    <div class="flex items-center justify-between rounded-xl bg-slate-100 px-3.5 py-2.5 text-slate-800 border border-slate-200">
                        <span>Previously unassigned:</span>
                        <span class="font-black text-sm" x-text="previewData?.previously_unassigned_count"></span>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                    <button type="button" @click="showPreviewModal = false" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">
                        Cancel / Edit
                    </button>
                    <button type="submit" class="rounded-xl bg-emerald-700 px-5 py-2.5 text-xs font-black text-white hover:bg-emerald-800 shadow-sm">
                        Confirm Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Monthly Executive Summary Cards -->
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Business Days</p>
            <p class="mt-1 text-2xl font-black text-slate-900">{{ $monthlySummary['total_days'] ?? 0 }}</p>
            <div class="mt-2 flex items-center gap-2 text-[11px] font-bold text-slate-600">
                <span class="text-emerald-600">{{ $monthlySummary['open_count'] ?? 0 }} Open</span>
                <span>·</span>
                <span class="text-slate-500">{{ $monthlySummary['closed_count'] ?? 0 }} Closed</span>
                <span>·</span>
                <span class="text-amber-600">{{ $monthlySummary['reopened_count'] ?? 0 }} Reopened</span>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Purchase Bills</p>
            <p class="mt-1 text-2xl font-black text-slate-900">{{ $monthlySummary['total_bills'] ?? 0 }}</p>
            <p class="mt-0.5 text-xs font-semibold text-slate-500">Bills Recorded</p>
            <div class="mt-2 text-[11px] font-bold text-slate-700">Total Vendor Bills</div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
            <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Advance Receives</p>
            <p class="mt-1 text-2xl font-black text-slate-900">{{ $monthlySummary['total_advance'] ?? 0 }}</p>
            <p class="mt-0.5 text-xs font-semibold text-slate-500">Advance GRNs</p>
            <div class="mt-2 text-[11px] font-bold text-slate-700">Direct Inward Loads</div>
        </div>

        <div class="rounded-2xl border {{ ($monthlySummary['pending_products_count'] ?? 0) > 0 ? 'border-rose-200 bg-rose-50/40' : 'border-slate-200 bg-white' }} p-4 shadow-xs">
            <p class="text-[10px] font-black uppercase tracking-wider {{ ($monthlySummary['pending_products_count'] ?? 0) > 0 ? 'text-rose-600' : 'text-slate-400' }}">Pending Bills</p>
            <p class="mt-1 text-2xl font-black {{ ($monthlySummary['pending_products_count'] ?? 0) > 0 ? 'text-rose-600' : 'text-slate-900' }}">{{ $monthlySummary['pending_products_count'] ?? 0 }}</p>
            <p class="mt-0.5 text-xs font-semibold text-slate-500">Unmatched Items</p>
            <div class="mt-2 text-[11px] font-bold text-rose-600">Pending Bill Entries</div>
        </div>

        <div class="rounded-2xl border {{ ($monthlySummary['unit_issues_count'] ?? 0) > 0 ? 'border-rose-200 bg-rose-50/40' : 'border-slate-200 bg-white' }} p-4 shadow-xs">
            <p class="text-[10px] font-black uppercase tracking-wider {{ ($monthlySummary['unit_issues_count'] ?? 0) > 0 ? 'text-rose-600' : 'text-slate-400' }}">Unit Issues</p>
            <p class="mt-1 text-2xl font-black {{ ($monthlySummary['unit_issues_count'] ?? 0) > 0 ? 'text-rose-600' : 'text-slate-900' }}">{{ $monthlySummary['unit_issues_count'] ?? 0 }}</p>
            <p class="mt-0.5 text-xs font-semibold text-slate-500">Mismatches</p>
            <div class="mt-2 text-[11px] font-bold text-slate-700">{{ ($monthlySummary['unit_issues_count'] ?? 0) > 0 ? 'Fix Required' : 'All Clean' }}</div>
        </div>

        <div class="rounded-2xl border {{ ($monthlySummary['closed_with_pending_count'] ?? 0) > 0 ? 'border-amber-200 bg-amber-50/40' : 'border-slate-200 bg-white' }} p-4 shadow-xs">
            <p class="text-[10px] font-black uppercase tracking-wider {{ ($monthlySummary['closed_with_pending_count'] ?? 0) > 0 ? 'text-amber-800' : 'text-slate-400' }}">Closed w/ Pending</p>
            <p class="mt-1 text-2xl font-black {{ ($monthlySummary['closed_with_pending_count'] ?? 0) > 0 ? 'text-amber-700' : 'text-slate-900' }}">{{ $monthlySummary['closed_with_pending_count'] ?? 0 }}</p>
            <p class="mt-0.5 text-xs font-semibold text-slate-500">Days With Exceptions</p>
            <div class="mt-2 text-[11px] font-bold text-amber-700">Audit Notes Recorded</div>
        </div>
    </div>

    <!-- Allotments History Table & Filters -->
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-base font-black text-slate-950">Current Product Allotments</h3>
                <p class="text-xs font-semibold text-slate-500">Active product assignments for Business Day reconciliation.</p>
            </div>
        </div>

        <!-- Filter Form -->
        <form method="GET" action="{{ route('admin.cashbook.purchaser-business-days.index') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 items-end">
            <input type="hidden" name="month" value="{{ $month }}">
            @if(request()->filled('warehouse_id'))
                <input type="hidden" name="warehouse_id" value="{{ request('warehouse_id') }}">
            @endif

            <div>
                <label class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Search Product / SKU</label>
                <input type="text" name="allotment_search" value="{{ $allotmentSearch }}" placeholder="e.g. Tomato or TOM-01" class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
            </div>

            <div>
                <label class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Category</label>
                <select name="allotment_category_id" class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                    <option value="">All Categories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ $allotmentCategoryId == $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Purchaser</label>
                <select name="allotment_purchaser_id" class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                    <option value="">All Purchasers</option>
                    @foreach($purchasers as $purchaser)
                        <option value="{{ $purchaser->id }}" {{ $allotmentPurchaserId == $purchaser->id ? 'selected' : '' }}>
                            {{ $purchaser->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="h-10 w-full rounded-xl bg-teal-600 text-xs font-black text-white hover:bg-teal-500 transition">
                    Filter Allotments
                </button>
            </div>
        </form>

        <!-- Allotment Table -->
        <div class="overflow-x-auto rounded-xl border border-slate-200">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50 text-[10px] font-black uppercase text-slate-500">
                        <th class="p-3">Product</th>
                        <th class="p-3">Category</th>
                        <th class="p-3">Purchaser</th>
                        <th class="p-3">Effective From</th>
                        <th class="p-3">Status</th>
                        <th class="p-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                    @forelse($allotmentsPaginated as $product)
                        @php $allotment = $product->currentPurchaserAllotment; @endphp
                        <tr class="hover:bg-slate-50/75">
                            <td class="p-3 font-bold text-slate-950">
                                <div>
                                    <span class="text-sm font-black text-slate-900">{{ $product->name }}</span>
                                    @if($product->sku)
                                        <span class="ml-1 text-[10px] font-mono text-slate-400">({{ $product->sku }})</span>
                                    @endif
                                </div>
                            </td>
                            <td class="p-3">
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">
                                    {{ $product->category?->name ?? 'Uncategorized' }}
                                </span>
                            </td>
                            <td class="p-3">
                                @if($allotment?->purchaser)
                                    <div class="flex items-center gap-1.5">
                                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-black">
                                            {{ strtoupper(substr($allotment->purchaser->name, 0, 1)) }}
                                        </span>
                                        <span class="font-bold text-slate-900">{{ $allotment->purchaser->name }}</span>
                                    </div>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-bold text-amber-800 border border-amber-200">
                                        Unassigned
                                    </span>
                                @endif
                            </td>
                            <td class="p-3 text-slate-600">
                                {{ $allotment?->effective_from ? $allotment->effective_from->format('d M Y') : '-' }}
                            </td>
                            <td class="p-3">
                                @if($allotment?->purchaser)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-black uppercase text-emerald-700 border border-emerald-200">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase text-slate-500">
                                        No Allotment
                                    </span>
                                @endif
                            </td>
                            <td class="p-3 text-right">
                                <a href="{{ route('admin.cashbook.finance.purchase.product-allotments.history', $product) }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-bold text-slate-700 hover:bg-slate-50">
                                    <i data-lucide="history" class="h-3.5 w-3.5"></i> View History
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-slate-400">
                                No products matching allotment criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($allotmentsPaginated->hasPages())
            <div class="border-t border-slate-100 pt-3">
                {{ $allotmentsPaginated->appends(request()->except('page'))->links() }}
            </div>
        @endif
    </div>

    <!-- Filter Bar for Business Days -->
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
        <form method="GET" action="{{ route('admin.cashbook.purchaser-business-days.index') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6 items-end">
            <!-- Month Picker -->
            <div>
                <label for="month" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Month</label>
                <input id="month" type="month" name="month" value="{{ $month }}" class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
            </div>

            <!-- Warehouse Filter -->
            <div>
                <label for="warehouse_id" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Warehouse</label>
                <select id="warehouse_id" name="warehouse_id" class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                    <option value="">All Warehouses</option>
                    @foreach ($warehouses as $wh)
                        <option value="{{ $wh->id }}" {{ (string) $selectedWarehouseId === (string) $wh->id ? 'selected' : '' }}>
                            {{ $wh->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Purchaser Filter -->
            <div>
                <label for="purchaser_id" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Opened By</label>
                <select id="purchaser_id" name="purchaser_id" class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                    <option value="">All Purchasers</option>
                    @foreach ($purchasers as $p)
                        <option value="{{ $p->id }}" {{ (string) $selectedPurchaserId === (string) $p->id ? 'selected' : '' }}>
                            {{ $p->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Status Filter -->
            <div>
                <label for="status" class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Status</label>
                <select id="status" name="status" class="mt-1.5 h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                    <option value="all" {{ $statusFilter === 'all' ? 'selected' : '' }}>All Statuses</option>
                    <option value="open" {{ $statusFilter === 'open' ? 'selected' : '' }}>Open Only</option>
                    <option value="closed" {{ $statusFilter === 'closed' ? 'selected' : '' }}>Closed Only</option>
                    <option value="reopened" {{ $statusFilter === 'reopened' ? 'selected' : '' }}>Reopened Only</option>
                </select>
            </div>

            <!-- Quick Toggles -->
            <div class="flex items-center gap-3">
                <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-700">
                    <input type="checkbox" name="pending_only" value="1" {{ $pendingOnly ? 'checked' : '' }} class="rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                    Pending Only
                </label>
            </div>

            <!-- Submit Button -->
            <div class="flex items-center gap-2">
                <button type="submit" class="h-10 w-full rounded-xl bg-teal-600 text-xs font-black text-white hover:bg-teal-500 shadow-xs transition">
                    Apply Filters
                </button>
                @if (request()->anyFilled(['warehouse_id', 'purchaser_id', 'status', 'pending_only']))
                    <a href="{{ route('admin.cashbook.purchaser-business-days.index', ['month' => $month]) }}" class="flex h-10 px-3 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-xs font-bold text-slate-600 hover:bg-slate-100">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Business Days Table -->
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
        <div class="border-b border-slate-200 bg-slate-50 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Business Days List</h3>
            <span class="rounded-full bg-slate-200 px-2.5 py-0.5 text-[11px] font-bold text-slate-700">{{ $daysData->count() }} Days</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50/50 text-[10px] font-black uppercase tracking-wider text-slate-400">
                        <th class="py-3 px-4">Date & Warehouse</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-center">Bills</th>
                        <th class="py-3 px-4 text-center">Advance</th>
                        <th class="py-3 px-4 text-center">Pending Items</th>
                        <th class="py-3 px-4 text-center">Unit Issues</th>
                        <th class="py-3 px-4">Opened By</th>
                        <th class="py-3 px-4">Closed By</th>
                        <th class="py-3 px-4 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($daysData as $item)
                        @php
                            $day = $item['day'];
                            $summary = $item['summary'];
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="py-4 px-4 whitespace-nowrap">
                                <span class="font-black text-slate-900 text-sm block">{{ $day->business_date->format('d M Y') }}</span>
                                <span class="text-[11px] font-bold text-slate-500 block">{{ $day->warehouse?->name ?? 'All Warehouses' }}</span>
                            </td>
                            <td class="py-4 px-4 whitespace-nowrap">
                                @if ($day->isOpen())
                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-emerald-800">OPEN</span>
                                @elseif ($day->isReopened())
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-amber-800">REOPENED</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-slate-700">CLOSED</span>
                                @endif
                            </td>
                            <td class="py-4 px-4 text-center whitespace-nowrap">
                                <span class="font-black text-slate-900 block text-xs">{{ $item['bills_count'] }}</span>
                                <span class="text-[10px] font-bold text-slate-500 block">{{ $item['bills_formatted_total'] }}</span>
                            </td>
                            <td class="py-4 px-4 text-center whitespace-nowrap">
                                <span class="font-black text-slate-900 block text-xs">{{ $item['advance_count'] }}</span>
                                <span class="text-[10px] font-bold text-slate-500 block">{{ $item['advance_formatted_total'] }}</span>
                            </td>
                            <td class="py-4 px-4 text-center whitespace-nowrap">
                                @if ($item['pending_count'] > 0)
                                    <span class="inline-flex items-center rounded-full bg-rose-100 px-2.5 py-0.5 text-[10px] font-black text-rose-800">
                                        {{ $item['pending_count'] }} pending
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-black text-emerald-800">
                                        Matched
                                    </span>
                                @endif
                            </td>
                            <td class="py-4 px-4 text-center whitespace-nowrap">
                                @if ($item['unit_issues_count'] > 0)
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-[10px] font-black text-amber-800">
                                        {{ $item['unit_issues_count'] }} issues
                                    </span>
                                @else
                                    <span class="text-slate-400 font-bold text-xs">--</span>
                                @endif
                            </td>
                            <td class="py-4 px-4 text-slate-600 text-[11px]">
                                <span class="font-bold text-slate-800">{{ $day->openedBy?->name ?? 'System' }}</span>
                                <span class="block text-[10px] text-slate-400">{{ $day->opened_at?->format('d M, h:i A') }}</span>
                            </td>
                            <td class="py-4 px-4 text-slate-600 text-[11px]">
                                @if ($day->closed_at)
                                    <span class="font-bold text-slate-800">{{ $day->closed_at->format('d M, h:i A') }}</span>
                                    <span class="block text-[10px] text-slate-400">by {{ $day->closedBy?->name ?? 'System' }}</span>
                                @else
                                    <span class="text-slate-400 font-semibold">--</span>
                                @endif
                            </td>
                            <td class="py-4 px-6 text-center whitespace-nowrap">
                                <a href="{{ route('admin.cashbook.purchaser-business-days.show', $day->uuid) }}" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-3 py-1.5 text-xs font-black text-white hover:bg-slate-800 shadow-xs transition">
                                    View Detail
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-12 text-center text-sm font-semibold text-slate-400">
                                No business day records found for the selected month and filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function productAllotmentApp(allProducts, categories) {
    return {
        allotmentSectionOpen: false,
        allProducts: allProducts || [],
        categories: categories || [],
        purchaser_user_id: '',
        effective_from: new Date().toISOString().split('T')[0],
        selectedCategories: [],
        selectedProducts: [],
        categorySearch: '',
        productSearch: '',
        categoryDropdownOpen: false,
        showPreviewModal: false,
        isLoadingPreview: false,
        previewData: null,

        get filteredCategories() {
            if (!this.categorySearch.trim()) return this.categories;
            const term = this.categorySearch.toLowerCase();
            return this.categories.filter(c => c.name.toLowerCase().includes(term));
        },

        get filteredProducts() {
            if (!this.productSearch.trim()) return this.allProducts;
            const term = this.productSearch.toLowerCase();
            return this.allProducts.filter(p => p.name.toLowerCase().includes(term) || (p.sku && p.sku.toLowerCase().includes(term)));
        },

        toggleCategory(catId) {
            const index = this.selectedCategories.indexOf(catId);
            if (index > -1) {
                this.selectedCategories.splice(index, 1);
                // Unselect products of this category
                const catProductIds = this.allProducts.filter(p => p.category_id === catId).map(p => p.id);
                this.selectedProducts = this.selectedProducts.filter(id => !catProductIds.includes(id));
            } else {
                this.selectedCategories.push(catId);
                // Auto-select products of this category
                const catProductIds = this.allProducts.filter(p => p.category_id === catId).map(p => p.id);
                catProductIds.forEach(id => {
                    if (!this.selectedProducts.includes(id)) {
                        this.selectedProducts.push(id);
                    }
                });
            }
        },

        selectAllVisibleProducts() {
            const visibleIds = this.filteredProducts.map(p => p.id);
            visibleIds.forEach(id => {
                if (!this.selectedProducts.includes(id)) {
                    this.selectedProducts.push(id);
                }
            });
        },

        clearAllProducts() {
            this.selectedProducts = [];
            this.selectedCategories = [];
        },

        toggleProduct(productId) {
            const index = this.selectedProducts.indexOf(productId);
            if (index > -1) {
                this.selectedProducts.splice(index, 1);
            } else {
                this.selectedProducts.push(productId);
            }
        },

        isProductSelected(productId) {
            return this.selectedProducts.includes(productId);
        },

        isCategorySelected(catId) {
            return this.selectedCategories.includes(catId);
        },

        async openPreview() {
            if (!this.purchaser_user_id) {
                alert('Please select a purchaser.');
                return;
            }
            if (this.selectedProducts.length === 0) {
                alert('Please select at least one product.');
                return;
            }
            if (!this.effective_from) {
                alert('Please select an effective from date.');
                return;
            }

            this.isLoadingPreview = true;
            try {
                const response = await fetch('{{ route('admin.cashbook.purchaser-business-days.allotments.preview') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        product_ids: this.selectedProducts,
                        purchaser_user_id: this.purchaser_user_id,
                        effective_from: this.effective_from
                    })
                });
                const res = await response.json();
                if (res.success) {
                    this.previewData = res.data;
                    this.showPreviewModal = true;
                } else {
                    alert(res.message || 'Error generating preview');
                }
            } catch (err) {
                console.error(err);
                alert('Failed to connect to server.');
            } finally {
                this.isLoadingPreview = false;
            }
        }
    };
}
</script>
@endsection
