@extends('admin.cashbook.layouts.app')

@section('title', 'Purchase Credit Notes — Green Leaf Cashbook')

@section('header_title')
    <i data-lucide="receipt-text" class="h-5 w-5 text-emerald-600"></i>
    <span>Purchase Credit Notes</span>
@endsection

@section('header_subtitle')
    Vendor returns, shortages, damage write-offs, price adjustments, and cash/bank refunds.
@endsection

@section('content')
@php
    $initialPurchaser = match (true) {
        ($filters['purchaser_id'] ?? '') === 'unassigned' => ['id' => 'unassigned', 'name' => 'Unassigned'],
        $selectedPurchaser !== null => ['id' => (string) $selectedPurchaser->id, 'name' => $selectedPurchaser->name],
        default => null,
    };
@endphp
<div class="mx-auto max-w-[96rem] space-y-6"
     x-data="purchaseCreditNoteDashboard({
         indexUrl: '{{ route('admin.cashbook.purchase-credit-notes.index') }}',
         searchUrl: '{{ route('admin.cashbook.purchase-credit-notes.search-vendors') }}',
         purchaserSearchUrl: '{{ route('admin.cashbook.purchase-credit-notes.search-purchasers') }}',
         summaryUrl: '{{ route('admin.cashbook.purchase-credit-notes.vendor-summary') }}',
         vendorDetailBaseUrl: '{{ url('admin/cashbook/finance/purchase/vendors') }}',
         createBaseUrl: '{{ route('admin.cashbook.purchase-credit-notes.create') }}',
         initialVendor: @js($selectedSupplier),
         initialPurchaser: @js($initialPurchaser),
         initialSummary: @js($summary),
         initialHasActiveFilters: @js($hasActiveFilters),
         initialFilters: @js([
             'date_preset' => $filters['date_preset'] ?? 'today',
             'start_date' => $filters['start_date'] ?? '',
             'end_date' => $filters['end_date'] ?? '',
             'status' => $filters['status'] ?? '',
             'reason' => $filters['reason'] ?? '',
             'money_effect' => $filters['money_effect'] ?? '',
             'q' => $filters['q'] ?? '',
         ])
     })"
     x-init="init()">
    @if (session('status'))
        <div class="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-semibold text-emerald-800">
            <i data-lucide="check-circle-2" class="h-4 w-4 shrink-0 text-emerald-600"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    {{-- 1. Top Header Action --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="text-xs font-medium text-slate-500">
            Credit note summary cards reflect <span class="font-bold text-slate-700">Active</span> notes within the selected filters. Vendor Financial Position reflects <span class="font-bold text-slate-700">Current All-Time</span> balances.
        </div>

        @can('cashbook.reconciliation.manage')
            <a :href="selectedVendor ? `${createBaseUrl}?supplier=${encodeURIComponent(selectedVendor.public_uuid)}` : createBaseUrl"
               class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 text-xs font-bold text-white shadow-xs transition hover:bg-emerald-700">
                <i data-lucide="plus" class="h-4 w-4"></i>
                <span>New Credit Note</span>
            </a>
        @endcan
    </div>

    {{-- 2. Searchable Vendor Selection --}}
    <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs" @click.outside="dropdownOpen = false">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="relative flex-1">
                <label class="mb-1.5 flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                    <i data-lucide="store" class="h-3.5 w-3.5 text-emerald-600"></i>
                    <span>Select Vendor (Filter &amp; Current Financial Position)</span>
                </label>

                <div class="relative">
                    <i data-lucide="search" class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400"></i>
                    <input type="text"
                           x-model="vendorQuery"
                           @focus="openSearch()"
                           @input="onVendorInput($event.target.value)"
                           placeholder="Search vendor by name or phone..."
                           autocomplete="off"
                           class="h-9 w-full rounded-xl border border-slate-200 bg-white pl-9 pr-9 text-xs font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none">

                    <button type="button"
                            x-show="selectedVendor || vendorQuery.length > 0"
                            x-cloak
                            @click="clearVendor()"
                            title="Clear selected vendor"
                            class="absolute right-2 top-1.5 inline-flex h-6 w-6 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        <i data-lucide="x" class="h-3.5 w-3.5"></i>
                    </button>
                </div>

                {{-- Search Dropdown --}}
                <div x-show="dropdownOpen"
                     x-cloak
                     class="absolute left-0 right-0 z-30 mt-1.5 max-h-72 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-lg">
                    <div x-show="searchingVendors" class="flex items-center gap-2 px-4 py-3 text-xs font-semibold text-slate-500">
                        <i data-lucide="loader-2" class="h-4 w-4 animate-spin text-emerald-600"></i>
                        <span>Searching vendors...</span>
                    </div>

                    <div x-show="searchError" x-cloak class="px-4 py-3 text-xs font-semibold text-rose-600" x-text="searchError"></div>

                    <div x-show="!searchingVendors && !searchError && vendorResults.length === 0" x-cloak class="px-4 py-4 text-center text-xs text-slate-500">
                        No matching vendors found.
                    </div>

                    <template x-for="vendor in vendorResults" :key="vendor.id">
                        <button type="button"
                                @click="selectVendor(vendor)"
                                class="flex w-full items-center justify-between border-b border-slate-100 px-4 py-2.5 text-left transition last:border-b-0 hover:bg-emerald-50/70">
                            <div>
                                <div class="text-xs font-bold text-slate-900" x-text="vendor.name"></div>
                                <div class="mt-0.5 text-[11px] text-slate-500" x-show="vendor.primary_phone" x-text="vendor.primary_phone"></div>
                            </div>
                            <span class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-700">
                                <span>Select</span>
                                <i data-lucide="check" class="h-3 w-3"></i>
                            </span>
                        </button>
                    </template>
                </div>
            </div>

            <div class="flex items-center gap-2 self-end lg:pb-0.5" x-show="selectedVendor" x-cloak>
                <span class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-800">
                    <i data-lucide="building-2" class="h-3.5 w-3.5 text-emerald-600"></i>
                    <span x-text="selectedVendor?.name"></span>
                </span>
                <button type="button"
                        @click="clearVendor()"
                        class="inline-flex h-8 items-center gap-1 rounded-xl border border-slate-200 bg-white px-2.5 text-[11px] font-bold text-slate-600 hover:bg-slate-50">
                    <i data-lucide="x" class="h-3.5 w-3.5"></i>
                    <span>Clear</span>
                </button>
            </div>
        </div>
    </div>

    {{-- 3. Vendor Financial Position Card (Visible only when vendor is selected) --}}
    <div x-show="selectedVendor"
         x-cloak
         class="rounded-2xl border border-emerald-200/80 bg-white p-5 shadow-2xs space-y-4">
        <div class="flex flex-col gap-3 border-b border-slate-100 pb-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-2.5">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <i data-lucide="landmark" class="h-4 w-4"></i>
                </span>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-sm font-black tracking-tight text-slate-900">
                            Vendor Financial Position
                        </h2>
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-600">
                            Current All-Time
                        </span>
                    </div>
                    <p class="text-[11px] font-medium text-slate-500">
                        Live vendor balance for <span class="font-bold text-slate-700" x-text="selectedVendor?.name"></span> (independent of date filters below).
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a x-show="moreDetailsUrl"
                   :href="moreDetailsUrl"
                   class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 shadow-2xs transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-800">
                    <span>More Details</span>
                    <i data-lucide="external-link" class="h-3.5 w-3.5"></i>
                </a>
            </div>
        </div>

        {{-- Loading State --}}
        <div x-show="loadingSummary" class="flex items-center gap-2 py-6 text-xs font-semibold text-slate-500">
            <i data-lucide="loader-2" class="h-4 w-4 animate-spin text-emerald-600"></i>
            <span>Loading current vendor financial position...</span>
        </div>

        {{-- Error State --}}
        <div x-show="!loadingSummary && summaryError" x-cloak class="flex items-center justify-between rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-semibold text-rose-800">
            <div class="flex items-center gap-2">
                <i data-lucide="alert-circle" class="h-4 w-4 text-rose-600"></i>
                <span x-text="summaryError"></span>
            </div>
            <button type="button"
                    @click="fetchVendorSummary(selectedVendor.id)"
                    class="rounded-lg border border-rose-300 bg-white px-2.5 py-1 text-[11px] font-bold text-rose-700 hover:bg-rose-100">
                Retry
            </button>
        </div>

        {{-- Summary Grid --}}
        <div x-show="!loadingSummary && !summaryError && vendorSummary" x-cloak class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-slate-200/80 bg-slate-50/60 p-3.5">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Total Purchases</div>
                <div class="mt-1.5 text-lg font-black text-slate-900" x-text="formatMoney(vendorSummary?.total_purchases)"></div>
            </div>

            <div class="rounded-xl border border-slate-200/80 bg-slate-50/60 p-3.5">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Total Settled</div>
                <div class="mt-1.5 text-lg font-black text-emerald-700" x-text="formatMoney(vendorSummary?.total_settled)"></div>
            </div>

            <div class="rounded-xl border border-amber-200/80 bg-amber-50/50 p-3.5">
                <div class="text-[11px] font-bold uppercase tracking-wider text-amber-700">Outstanding Balance</div>
                <div class="mt-1.5 text-lg font-black text-amber-800" x-text="formatMoney(vendorSummary?.outstanding_balance)"></div>
            </div>

            <div class="rounded-xl border border-slate-200/80 bg-slate-50/60 p-3.5">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Pending Bills Count</div>
                <div class="mt-1.5 text-lg font-black text-slate-900" x-text="vendorSummary?.pending_bills_count ?? 0"></div>
            </div>

            <div class="rounded-xl border border-blue-200/80 bg-blue-50/50 p-3.5">
                <div class="text-[11px] font-bold uppercase tracking-wider text-blue-700">Total Open Vendor Advances</div>
                <div class="mt-1.5 text-lg font-black text-blue-800" x-text="formatMoney(vendorSummary?.total_open_vendor_advances)"></div>
            </div>

            <div class="rounded-xl border border-emerald-200/80 bg-emerald-50/50 p-3.5">
                <div class="text-[11px] font-bold uppercase tracking-wider text-emerald-700">Available Credit Note Balance</div>
                <div class="mt-1.5 text-lg font-black text-emerald-800" x-text="formatMoney(vendorSummary?.available_credit_note_balance)"></div>
                <div class="mt-0.5 text-[10px] font-semibold text-emerald-700/80">Subset of Open Vendor Advances</div>
            </div>

            <div class="rounded-xl border border-slate-200/80 bg-slate-50/60 p-3.5">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Total Active Credit Notes</div>
                <div class="mt-1.5 text-lg font-black text-slate-900" x-text="vendorSummary?.total_active_credit_notes ?? 0"></div>
            </div>

            <div class="rounded-xl border border-slate-200/80 bg-slate-50/60 p-3.5">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Total Active Credit Note Amount</div>
                <div class="mt-1.5 text-lg font-black text-slate-900" x-text="formatMoney(vendorSummary?.total_active_credit_note_amount)"></div>
            </div>
        </div>
    </div>

    {{-- 4. Existing Credit Note Summary Cards (Date & Filter Scoped) --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Vendor Credit (Filtered)</span>
                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <i data-lucide="wallet" class="h-4 w-4"></i>
                </span>
            </div>
            <div class="mt-2 text-2xl font-black tracking-tight text-emerald-700" x-text="formatMoney(listSummary.vendor_credit_total)">
                ₹{{ number_format($summary['vendor_credit_total'], 2) }}
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Money IN (Filtered)</span>
                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                    <i data-lucide="arrow-down-left" class="h-4 w-4"></i>
                </span>
            </div>
            <div class="mt-2 text-2xl font-black tracking-tight text-blue-700" x-text="formatMoney(listSummary.money_in_total)">
                ₹{{ number_format($summary['money_in_total'], 2) }}
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Money OUT (Filtered)</span>
                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                    <i data-lucide="arrow-up-right" class="h-4 w-4"></i>
                </span>
            </div>
            <div class="mt-2 text-2xl font-black tracking-tight text-amber-700" x-text="formatMoney(listSummary.money_out_total)">
                ₹{{ number_format($summary['money_out_total'], 2) }}
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Total Credit Notes (Filtered)</span>
                <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                    <i data-lucide="file-stack" class="h-4 w-4"></i>
                </span>
            </div>
            <div class="mt-2 text-2xl font-black tracking-tight text-slate-900" x-text="Number(listSummary.total_count || 0).toLocaleString('en-IN')">
                {{ number_format($summary['total_count']) }}
            </div>
        </div>
    </div>

    {{-- 5. Advanced Filters (Async, No Full-Page Reload) --}}
    <form @submit.prevent="applyFilters(1)"
          class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs space-y-3">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
            {{-- Purchaser Searchable Filter --}}
            <div class="relative" @click.outside="purchaserDropdownOpen = false">
                <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Purchaser</label>
                <div class="relative">
                    <input type="text"
                           x-model="purchaserQuery"
                           @focus="openPurchaserSearch()"
                           @input="onPurchaserInput($event.target.value)"
                           placeholder="All Purchasers"
                           autocomplete="off"
                           class="h-9 w-full rounded-xl border border-slate-200 bg-white px-2.5 pr-7 text-xs font-semibold text-slate-800 focus:border-emerald-500 focus:outline-none">
                    <button type="button"
                            x-show="selectedPurchaser || purchaserQuery.length > 0"
                            x-cloak
                            @click="clearPurchaser()"
                            title="All Purchasers"
                            class="absolute right-1.5 top-1.5 inline-flex h-6 w-6 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        <i data-lucide="x" class="h-3 w-3"></i>
                    </button>
                </div>

                <div x-show="purchaserDropdownOpen"
                     x-cloak
                     class="absolute left-0 right-0 z-30 mt-1 max-h-60 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-lg">
                    <button type="button"
                            @click="selectPurchaser(null)"
                            class="flex w-full items-center justify-between border-b border-slate-100 px-3 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-slate-50">
                        <span>All Purchasers</span>
                    </button>
                    <button type="button"
                            @click="selectPurchaser({ id: 'unassigned', name: 'Unassigned' })"
                            class="flex w-full items-center justify-between border-b border-slate-100 px-3 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-amber-50/60">
                        <span>Unassigned</span>
                    </button>
                    <div x-show="searchingPurchasers" class="flex items-center gap-1.5 px-3 py-2 text-[11px] text-slate-500">
                        <i data-lucide="loader-2" class="h-3.5 w-3.5 animate-spin text-emerald-600"></i>
                        <span>Searching...</span>
                    </div>
                    <template x-for="p in purchaserResults" :key="p.id">
                        <button type="button"
                                @click="selectPurchaser({ id: String(p.id), name: p.name })"
                                class="flex w-full flex-col border-b border-slate-100 px-3 py-2 text-left last:border-b-0 hover:bg-emerald-50/70">
                            <span class="text-xs font-bold text-slate-900" x-text="p.name"></span>
                        </button>
                    </template>
                </div>
            </div>

            {{-- Date Preset --}}
            <div>
                <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Date Range</label>
                <select x-model="filters.date_preset"
                        @change="onDatePresetChange()"
                        class="h-9 w-full rounded-xl border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-800 focus:border-emerald-500 focus:outline-none">
                    <option value="today">Today</option>
                    <option value="yesterday">Yesterday</option>
                    <option value="last_7_days">Last 7 Days</option>
                    <option value="last_30_days">Last 30 Days</option>
                    <option value="this_month">This Month</option>
                    <option value="last_month">Last Month</option>
                    <option value="custom">Custom Range</option>
                    <option value="all_time">All Time</option>
                </select>
            </div>

            {{-- Status --}}
            <div>
                <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Status</label>
                <select x-model="filters.status"
                        @change="applyFilters(1)"
                        class="h-9 w-full rounded-xl border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-800 focus:border-emerald-500 focus:outline-none">
                    <option value="">All Statuses</option>
                    @foreach ($statuses as $st)
                        <option value="{{ $st->value }}">{{ $st->label() }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Reason --}}
            <div>
                <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Reason</label>
                <select x-model="filters.reason"
                        @change="applyFilters(1)"
                        class="h-9 w-full rounded-xl border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-800 focus:border-emerald-500 focus:outline-none">
                    <option value="">All Reasons</option>
                    @foreach ($reasons as $rsn)
                        <option value="{{ $rsn->value }}">{{ $rsn->label() }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Money Effect --}}
            <div>
                <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Money Effect</label>
                <select x-model="filters.money_effect"
                        @change="applyFilters(1)"
                        class="h-9 w-full rounded-xl border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-800 focus:border-emerald-500 focus:outline-none">
                    <option value="">All Effects</option>
                    @foreach ($moneyEffects as $me)
                        <option value="{{ $me->value }}">{{ $me->label() }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Search & Clear All --}}
            <div>
                <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Credit Note #</label>
                <div class="flex gap-1.5">
                    <input type="text"
                           x-model="filters.q"
                           @input="onKeywordInput()"
                           placeholder="PCN # or notes..."
                           class="h-9 w-full rounded-xl border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-800 focus:border-emerald-500 focus:outline-none">
                    <button type="submit"
                            class="inline-flex h-9 shrink-0 items-center justify-center rounded-xl bg-slate-900 px-3 text-xs font-bold text-white hover:bg-slate-800">
                        <i data-lucide="search" class="h-3.5 w-3.5"></i>
                    </button>
                    <button type="button"
                            x-show="hasActiveFilters"
                            x-cloak
                            @click="clearAllFilters()"
                            title="Clear All Filters"
                            class="inline-flex h-9 shrink-0 items-center justify-center gap-1 rounded-xl border border-slate-200 bg-white px-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50">
                        <i data-lucide="rotate-ccw" class="h-3.5 w-3.5"></i>
                        <span class="hidden xl:inline">Clear</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Custom Date Range Inputs --}}
        <div x-show="filters.date_preset === 'custom'" x-cloak class="grid grid-cols-1 gap-3 border-t border-slate-100 pt-3 sm:grid-cols-3 lg:grid-cols-6">
            <div>
                <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">From Date</label>
                <input type="date"
                       x-model="filters.start_date"
                       @change="applyFilters(1)"
                       class="h-9 w-full rounded-xl border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-800 focus:border-emerald-500 focus:outline-none">
            </div>
            <div>
                <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">To Date</label>
                <input type="date"
                       x-model="filters.end_date"
                       @change="applyFilters(1)"
                       class="h-9 w-full rounded-xl border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-800 focus:border-emerald-500 focus:outline-none">
            </div>
            <div x-show="listError" x-cloak class="flex items-end text-xs font-semibold text-rose-600 sm:col-span-2" x-text="listError"></div>
        </div>
    </form>

    {{-- 6. Paginated Credit Note Table (Async Container) --}}
    <div class="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-2xs"
         @click="onTableClick($event)">
        <div x-show="loadingList"
             x-cloak
             class="absolute right-4 top-3 z-10 inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[11px] font-bold text-emerald-700 shadow-2xs">
            <i data-lucide="loader-2" class="h-3.5 w-3.5 animate-spin"></i>
            <span>Updating...</span>
        </div>

        <div x-ref="tableContainer">
            @include('admin.cashbook.purchase-credit-notes.partials.table', ['creditNotes' => $creditNotes])
        </div>
    </div>
</div>

@push('scripts')
<script>
function purchaseCreditNoteDashboard(config) {
    return {
        indexUrl: config.indexUrl,
        searchUrl: config.searchUrl,
        purchaserSearchUrl: config.purchaserSearchUrl,
        summaryUrl: config.summaryUrl,
        vendorDetailBaseUrl: config.vendorDetailBaseUrl,
        createBaseUrl: config.createBaseUrl,

        selectedVendor: config.initialVendor || null,
        vendorQuery: config.initialVendor ? config.initialVendor.name : '',
        vendorResults: [],
        dropdownOpen: false,
        searchingVendors: false,
        searchError: '',
        searchTimer: null,
        searchSeq: 0,
        searchController: null,

        selectedPurchaser: config.initialPurchaser || null,
        purchaserQuery: config.initialPurchaser ? config.initialPurchaser.name : '',
        purchaserResults: [],
        purchaserDropdownOpen: false,
        searchingPurchasers: false,
        purchaserTimer: null,
        purchaserSeq: 0,
        purchaserController: null,

        vendorSummary: null,
        moreDetailsUrl: config.initialVendor ? `${config.vendorDetailBaseUrl}/${encodeURIComponent(config.initialVendor.public_uuid)}` : '',
        loadingSummary: false,
        summaryError: '',
        summarySeq: 0,
        summaryController: null,

        filters: {
            date_preset: config.initialFilters?.date_preset || 'today',
            start_date: config.initialFilters?.start_date || '',
            end_date: config.initialFilters?.end_date || '',
            status: config.initialFilters?.status || '',
            reason: config.initialFilters?.reason || '',
            money_effect: config.initialFilters?.money_effect || '',
            q: config.initialFilters?.q || '',
        },
        listSummary: config.initialSummary || { vendor_credit_total: 0, money_in_total: 0, money_out_total: 0, total_count: 0 },
        hasActiveFilters: Boolean(config.initialHasActiveFilters),
        loadingList: false,
        listError: '',
        listSeq: 0,
        listController: null,
        keywordTimer: null,

        init() {
            if (this.selectedVendor) {
                this.fetchVendorSummary(this.selectedVendor.id);
            }
            window.addEventListener('popstate', () => {
                window.location.reload();
            });
        },

        openSearch() {
            this.dropdownOpen = true;
            const q = (this.selectedVendor && this.vendorQuery === this.selectedVendor.name) ? '' : this.vendorQuery;
            this.fetchVendors(q);
        },

        onVendorInput(val) {
            this.dropdownOpen = true;
            this.searchError = '';
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => this.fetchVendors(val), 280);
        },

        async fetchVendors(q) {
            const mySeq = ++this.searchSeq;
            if (this.searchController) {
                this.searchController.abort();
            }
            this.searchController = new AbortController();
            this.searchingVendors = true;
            this.searchError = '';

            try {
                const res = await fetch(`${this.searchUrl}?q=${encodeURIComponent(q || '')}&limit=20`, {
                    headers: { 'Accept': 'application/json' },
                    signal: this.searchController.signal
                });
                if (!res.ok) {
                    throw new Error('Failed to search vendors.');
                }
                const data = await res.json();
                if (mySeq === this.searchSeq) {
                    this.vendorResults = data.items || [];
                    this.$nextTick(() => window.lucide?.createIcons());
                }
            } catch (e) {
                if (e.name !== 'AbortError' && mySeq === this.searchSeq) {
                    this.searchError = 'Unable to load vendors.';
                    this.vendorResults = [];
                }
            } finally {
                if (mySeq === this.searchSeq) {
                    this.searchingVendors = false;
                }
            }
        },

        selectVendor(vendor) {
            this.selectedVendor = vendor;
            this.vendorQuery = vendor.name;
            this.dropdownOpen = false;
            this.moreDetailsUrl = `${this.vendorDetailBaseUrl}/${encodeURIComponent(vendor.public_uuid)}`;
            this.fetchVendorSummary(vendor.id);
            this.applyFilters(1);
        },

        clearVendor() {
            const hadSelected = Boolean(this.selectedVendor);
            this.selectedVendor = null;
            this.vendorQuery = '';
            this.vendorSummary = null;
            this.summaryError = '';
            this.moreDetailsUrl = '';
            this.dropdownOpen = false;
            if (this.summaryController) {
                this.summaryController.abort();
            }
            if (hadSelected) {
                this.applyFilters(1);
            }
        },

        openPurchaserSearch() {
            this.purchaserDropdownOpen = true;
            const q = (this.selectedPurchaser && this.purchaserQuery === this.selectedPurchaser.name) ? '' : this.purchaserQuery;
            this.fetchPurchasers(q);
        },

        onPurchaserInput(val) {
            this.purchaserDropdownOpen = true;
            clearTimeout(this.purchaserTimer);
            this.purchaserTimer = setTimeout(() => this.fetchPurchasers(val), 280);
        },

        async fetchPurchasers(q) {
            const mySeq = ++this.purchaserSeq;
            if (this.purchaserController) {
                this.purchaserController.abort();
            }
            this.purchaserController = new AbortController();
            this.searchingPurchasers = true;

            try {
                const res = await fetch(`${this.purchaserSearchUrl}?q=${encodeURIComponent(q || '')}&limit=20`, {
                    headers: { 'Accept': 'application/json' },
                    signal: this.purchaserController.signal
                });
                if (!res.ok) {
                    throw new Error('Failed to search purchasers.');
                }
                const data = await res.json();
                if (mySeq === this.purchaserSeq) {
                    this.purchaserResults = data.items || [];
                }
            } catch (e) {
                if (e.name !== 'AbortError' && mySeq === this.purchaserSeq) {
                    this.purchaserResults = [];
                }
            } finally {
                if (mySeq === this.purchaserSeq) {
                    this.searchingPurchasers = false;
                }
            }
        },

        selectPurchaser(purchaser) {
            this.selectedPurchaser = purchaser;
            this.purchaserQuery = purchaser ? purchaser.name : '';
            this.purchaserDropdownOpen = false;
            this.applyFilters(1);
        },

        clearPurchaser() {
            const hadSelected = Boolean(this.selectedPurchaser);
            this.selectedPurchaser = null;
            this.purchaserQuery = '';
            this.purchaserDropdownOpen = false;
            if (hadSelected) {
                this.applyFilters(1);
            }
        },

        onDatePresetChange() {
            if (this.filters.date_preset !== 'custom') {
                this.filters.start_date = '';
                this.filters.end_date = '';
                this.applyFilters(1);
            }
        },

        onKeywordInput() {
            clearTimeout(this.keywordTimer);
            this.keywordTimer = setTimeout(() => this.applyFilters(1), 280);
        },

        clearAllFilters() {
            const hadVendor = Boolean(this.selectedVendor);
            this.selectedVendor = null;
            this.vendorQuery = '';
            this.vendorSummary = null;
            this.summaryError = '';
            this.moreDetailsUrl = '';
            this.selectedPurchaser = null;
            this.purchaserQuery = '';
            this.filters = {
                date_preset: 'today',
                start_date: '',
                end_date: '',
                status: '',
                reason: '',
                money_effect: '',
                q: '',
            };
            if (hadVendor && this.summaryController) {
                this.summaryController.abort();
            }
            this.applyFilters(1);
        },

        buildQueryParams(page = 1) {
            const params = new URLSearchParams();
            if (this.selectedVendor) {
                params.set('supplier_id', String(this.selectedVendor.id));
            }
            if (this.selectedPurchaser) {
                params.set('purchaser_id', String(this.selectedPurchaser.id));
            }
            if (this.filters.date_preset && this.filters.date_preset !== 'today') {
                params.set('date_preset', this.filters.date_preset);
            }
            if (this.filters.date_preset === 'custom') {
                params.set('date_preset', 'custom');
                if (this.filters.start_date) params.set('start_date', this.filters.start_date);
                if (this.filters.end_date) params.set('end_date', this.filters.end_date);
            }
            if (this.filters.status) params.set('status', this.filters.status);
            if (this.filters.reason) params.set('reason', this.filters.reason);
            if (this.filters.money_effect) params.set('money_effect', this.filters.money_effect);
            if (this.filters.q.trim() !== '') params.set('q', this.filters.q.trim());
            if (page > 1) params.set('page', String(page));
            return params;
        },

        async applyFilters(page = 1, pushHistory = true) {
            const params = this.buildQueryParams(page);
            const qs = params.toString();
            const nextUrl = qs ? `${this.indexUrl}?${qs}` : this.indexUrl;

            if (pushHistory) {
                window.history.pushState({}, '', nextUrl);
            }

            await this.fetchFilteredList(nextUrl);
        },

        async fetchFilteredList(url) {
            const mySeq = ++this.listSeq;
            if (this.listController) {
                this.listController.abort();
            }
            this.listController = new AbortController();
            this.loadingList = true;
            this.listError = '';

            try {
                const separator = url.includes('?') ? '&' : '?';
                const res = await fetch(`${url}${separator}async=1`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    signal: this.listController.signal
                });
                const data = await res.json();
                if (!res.ok) {
                    throw new Error(data.message || 'Unable to filter credit notes.');
                }
                if (mySeq === this.listSeq) {
                    this.listSummary = data.summary || this.listSummary;
                    this.hasActiveFilters = Boolean(data.has_active_filters);
                    if (this.$refs.tableContainer && typeof data.table_html === 'string') {
                        this.$refs.tableContainer.innerHTML = data.table_html;
                    }
                    this.$nextTick(() => window.lucide?.createIcons());
                }
            } catch (e) {
                if (e.name !== 'AbortError' && mySeq === this.listSeq) {
                    this.listError = e.message || 'Failed to update credit notes.';
                }
            } finally {
                if (mySeq === this.listSeq) {
                    this.loadingList = false;
                }
            }
        },

        onTableClick(event) {
            const link = event.target.closest('[data-pcn-pagination] a');
            if (!link || !link.href) {
                return;
            }
            event.preventDefault();
            window.history.pushState({}, '', link.href);
            this.fetchFilteredList(link.href);
        },

        async fetchVendorSummary(supplierId) {
            const mySeq = ++this.summarySeq;
            if (this.summaryController) {
                this.summaryController.abort();
            }
            this.summaryController = new AbortController();
            this.loadingSummary = true;
            this.summaryError = '';

            try {
                const res = await fetch(`${this.summaryUrl}?supplier_id=${encodeURIComponent(supplierId)}`, {
                    headers: { 'Accept': 'application/json' },
                    signal: this.summaryController.signal
                });
                if (!res.ok) {
                    throw new Error('Failed to fetch vendor financial summary.');
                }
                const data = await res.json();
                if (mySeq === this.summarySeq) {
                    this.vendorSummary = data.summary || null;
                    if (data.more_details_url) {
                        this.moreDetailsUrl = data.more_details_url;
                    }
                    this.$nextTick(() => window.lucide?.createIcons());
                }
            } catch (e) {
                if (e.name !== 'AbortError' && mySeq === this.summarySeq) {
                    this.summaryError = 'Failed to load vendor financial position.';
                    this.vendorSummary = null;
                }
            } finally {
                if (mySeq === this.summarySeq) {
                    this.loadingSummary = false;
                }
            }
        },

        formatMoney(val) {
            const num = Number(val || 0);
            return '₹' + num.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    };
}
</script>
@endpush
@endsection
