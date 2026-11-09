@extends('admin.cashbook.layouts.app')

@section('title', ($isEdit ? 'Edit Draft — ' . $creditNote->credit_note_number : 'Create Shop Credit Note') . ' — Green Leaf Cashbook')

@section('header_title')
    <i data-lucide="file-plus-2" class="w-5 h-5 text-emerald-600"></i>
    <span>{{ $isEdit ? 'Edit Shop Credit Note Draft' : 'Create Shop Credit Note' }}</span>
@endsection

@section('header_subtitle')
    Multi-source historical invoice adjustments, return grading, damage write-offs, and commercial discounts.
@endsection

@section('content')
<div class="mx-auto max-w-[96rem] space-y-6"
    x-data="shopCreditNoteEditor({
        isEdit: {{ $isEdit ? 'true' : 'false' }},
        draftId: {{ $isEdit ? $creditNote->id : 'null' }},
        draftNumber: '{{ $isEdit ? $creditNote->credit_note_number : '' }}',
        lockVersion: {{ $isEdit ? (int) $creditNote->lock_version : 1 }},
        expectedUpdatedAt: '{{ $isEdit && $creditNote->updated_at ? $creditNote->updated_at->toISOString() : '' }}',
        selectedShopId: {{ $selectedShopId ? (int) $selectedShopId : 'null' }},
        defaultDate: '{{ $defaultDate }}',
        notes: '{{ $isEdit ? addslashes($creditNote->notes ?? '') : '' }}',
        searchLinesUrl: '{{ route('admin.cashbook.reports.shops.credit-notes.drafts.search-lines') }}',
        saveUrl: '{{ $isEdit ? route('admin.cashbook.reports.shops.credit-notes.drafts.update', $creditNote) : route('admin.cashbook.reports.shops.credit-notes.drafts.store') }}',
        redirectIndexUrl: '{{ route('admin.cashbook.reports.shops.credit-notes') }}',
        initialItems: @js($isEdit ? $creditNote->items->where('line_type.value', 'product_adjustment')->values()->map(function ($it) {
            return [
                'source_shop_invoice_id' => $it->source_shop_invoice_id,
                'shop_invoice_item_id' => $it->shop_invoice_item_id,
                'invoice_number' => $it->sourceInvoice?->invoice_number ?? "INV-{$it->source_shop_invoice_id}",
                'invoice_date' => $it->sourceInvoice?->business_date?->toDateString() ?? '',
                'product_id' => $it->product_id,
                'product_name' => $it->product?->name ?? $it->product_name,
                'product_grade' => $it->product_grade,
                'unit' => $it->price_unit ?: ($it->unit ?: 'kg'),
                'original_quantity' => (float) $it->original_quantity,
                'original_unit_price' => (float) $it->original_unit_price,
                'original_line_total' => (float) $it->original_line_total,
                'remaining_quantity' => (float) ($it->original_quantity),
                'credit_quantity' => (float) $it->adjusted_quantity,
                'credit_rate' => (float) ($it->credit_rate ?: $it->adjusted_unit_price),
                'line_credit_amount' => (float) $it->line_credit_amount,
                'adjustment_type' => match ($it->reason_type?->value) {
                    'return_to_warehouse' => 'RTN',
                    'damage_wastage' => 'DMG',
                    'grade_downgrade' => 'GRD',
                    default => 'ERR',
                },
                'reason_type' => $it->reason_type?->value ?? 'price_correction',
                'inventory_action' => $it->inventory_action?->value ?? 'none',
                'expected_returned_grade' => $it->received_grade ?? $it->product_grade,
                'damage_reason' => str_contains((string) $it->notes, 'damage_reason:') ? trim(explode('damage_reason:', (string) $it->notes)[1] ?? 'transit_damage') : 'transit_damage',
                'notes' => preg_replace('/damage_reason:\s*[a-z_]+/i', '', (string) ($it->notes ?? '')),
            ];
        }) : []),
        initialDiscounts: @js($isEdit ? $creditNote->items->where('line_type.value', 'commercial_discount')->values()->map(function ($disc) {
            return [
                'line_credit_amount' => (float) $disc->line_credit_amount,
                'notes' => $disc->notes ?: 'Commercial Discount',
                'source_shop_invoice_id' => $disc->source_shop_invoice_id,
            ];
        }) : [])
    })">

    <!-- Top Navigation & Breadcrumbs -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('admin.cashbook.reports.shops.credit-notes') }}"
                class="inline-flex h-9 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 hover:bg-slate-50 transition shadow-2xs">
                <i data-lucide="arrow-left" class="h-3.5 w-3.5 text-slate-400"></i>
                <span>Credit Notes</span>
            </a>
            <span class="text-slate-300">/</span>
            <span class="text-xs font-bold text-slate-500">Shop Credit Note</span>
            @if ($isEdit)
                <span class="rounded-lg bg-amber-100 border border-amber-200 px-2 py-0.5 text-xs font-mono font-bold text-amber-900">
                    {{ $creditNote->credit_note_number }}
                </span>
                <span class="rounded-lg bg-slate-100 px-2 py-0.5 text-[10px] font-mono font-bold text-slate-600">
                    v{{ $creditNote->lock_version }}
                </span>
            @else
                <span class="rounded-lg bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-xs font-bold text-emerald-800">
                    New Draft
                </span>
            @endif
        </div>

        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 border border-amber-200 px-3 py-1 text-xs font-extrabold text-amber-800">
                <i data-lucide="clock" class="w-3.5 h-3.5 text-amber-600"></i>
                <span>Draft — Not Posted</span>
            </span>
        </div>
    </div>

    <!-- Alert Banners -->
    @if (session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4 shadow-xs flex items-center gap-3">
            <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600 shrink-0"></i>
            <span class="text-xs font-bold text-emerald-900">{{ session('success') }}</span>
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-2xl border border-rose-200 bg-rose-50/80 p-4 shadow-xs flex items-center gap-3">
            <i data-lucide="alert-circle" class="w-5 h-5 text-rose-600 shrink-0"></i>
            <span class="text-xs font-bold text-rose-800">{{ session('error') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50/70 p-4 shadow-xs">
            <div class="flex items-start gap-3">
                <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-600 shrink-0 mt-0.5"></i>
                <div class="space-y-1">
                    <h4 class="text-xs font-bold text-rose-900">Please review the following errors:</h4>
                    <ul class="list-disc list-inside text-xs font-semibold text-rose-700 space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <!-- Concurrency Conflict Banner -->
    <div x-show="concurrencyConflict" x-cloak class="rounded-2xl border border-amber-300 bg-amber-50 p-4 shadow-xs">
        <div class="flex items-start justify-between gap-3">
            <div class="flex items-start gap-3">
                <i data-lucide="refresh-cw" class="w-5 h-5 text-amber-600 shrink-0 mt-0.5"></i>
                <div>
                    <h4 class="text-xs font-bold text-amber-900">Draft Modified by Another User</h4>
                    <p class="text-xs text-amber-800 mt-0.5">This draft was updated concurrently by another user. Please reload the draft to review the latest changes before reapplying your edits.</p>
                </div>
            </div>
            <button type="button" @click="window.location.reload()"
                class="rounded-xl bg-amber-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-amber-700 transition shrink-0">
                Reload Draft
            </button>
        </div>
    </div>

    <!-- Hidden Fallback Form for Standard HTML POST/PUT -->
    <form id="creditNoteFallbackForm" method="POST" :action="saveUrl" class="hidden">
        @csrf
        <template x-if="isEdit">
            <input type="hidden" name="_method" value="PUT">
        </template>
        <input type="hidden" name="payload_json" id="payloadJsonInput">
    </form>

    <!-- SECTION 1: HEADER & SHOP SETTINGS -->
    <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 font-black text-xs">
                    1
                </div>
                <h3 class="text-sm font-black text-slate-900">Credit Note Scope & Date</h3>
            </div>
            <span class="text-xs font-medium text-slate-400">Step 1: Select shop and effective date</span>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <!-- Shop Selector -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                    Shop <span class="text-rose-500">*</span>
                </label>
                <select x-model="shopId" @change="onShopChange()" :disabled="isEdit"
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 disabled:bg-slate-100 disabled:text-slate-500">
                    <option value="">-- Choose Shop --</option>
                    @foreach ($shops as $shop)
                        <option value="{{ $shop->id }}" @selected($selectedShopId === $shop->id)>
                            {{ $shop->name }} ({{ $shop->code }})
                        </option>
                    @endforeach
                </select>
                <template x-if="isEdit">
                    <p class="text-[10px] text-slate-400 mt-1">Shop cannot be changed on an existing draft.</p>
                </template>
            </div>

            <!-- Credit Note Date -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                    Credit Note Date <span class="text-rose-500">*</span>
                </label>
                <input type="date" x-model="creditNoteDate"
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                <p class="text-[10px] text-slate-400 mt-1">Default is today; must fall within an open accounting period.</p>
            </div>

            <!-- Reference / Notes -->
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                    General Reference / Notes
                </label>
                <input type="text" x-model="notes" placeholder="e.g. Return of excess stock & transit damage claim"
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
            </div>
        </div>
    </div>

    <!-- SECTION 2: HISTORICAL INVOICE SEARCH (SEARCH FIRST) -->
    <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs space-y-4"
        :class="!shopId ? 'opacity-60 pointer-events-none' : ''">

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 font-black text-xs">
                    2
                </div>
                <div>
                    <h3 class="text-sm font-black text-slate-900">Historical Invoice Lines Search</h3>
                    <p class="text-xs text-slate-500">Search billed products across previous finalized invoices for the selected shop.</p>
                </div>
            </div>
            <template x-if="!shopId">
                <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-500">
                    Select a shop in Step 1 first
                </span>
            </template>
        </div>

        <!-- Search Filters Form -->
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 items-end bg-slate-50/70 p-3.5 rounded-2xl border border-slate-100">
            <!-- Period Filter: Month -->
            <div>
                <label class="block text-[11px] font-bold text-slate-600 mb-1">Invoice Month</label>
                <input type="month" x-model="searchMonth" @change="searchLines()"
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
            </div>

            <!-- Product / Invoice Keyword Search -->
            <div class="lg:col-span-2">
                <label class="block text-[11px] font-bold text-slate-600 mb-1">Product Name / SKU / Invoice #</label>
                <div class="relative">
                    <input type="text" x-model="searchQuery" @keydown.enter.prevent="searchLines()"
                        placeholder="Search by product name, SKU or invoice number..."
                        class="w-full rounded-xl border border-slate-200 bg-white pl-9 pr-3 py-1.5 text-xs font-medium text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-2"></i>
                </div>
            </div>

            <!-- Search Action Button -->
            <div>
                <button type="button" @click="searchLines()" :disabled="isSearching || !shopId"
                    class="w-full inline-flex items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-700 transition shadow-xs disabled:opacity-50">
                    <template x-if="isSearching">
                        <span class="inline-flex items-center gap-1">
                            <i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin"></i>
                            <span>Searching...</span>
                        </span>
                    </template>
                    <template x-if="!isSearching">
                        <span class="inline-flex items-center gap-1">
                            <i data-lucide="search" class="w-3.5 h-3.5"></i>
                            <span>Find Invoice Lines</span>
                        </span>
                    </template>
                </button>
            </div>
        </div>

        <!-- Search Results List -->
        <div x-show="searchResults.length > 0" class="space-y-2">
            <div class="flex items-center justify-between text-xs text-slate-500 px-1">
                <span>Matching Historical Invoice Lines (<span class="font-bold text-slate-800" x-text="searchResults.length"></span> found)</span>
                <span class="text-[11px] text-slate-400">Click "+ Add to Draft" to select source lines for adjustment</span>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 border-b border-slate-200 text-[10px] font-black uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="py-2.5 px-3">Invoice # / Date</th>
                            <th class="py-2.5 px-3">Product & Grade</th>
                            <th class="py-2.5 px-3 text-right">Billed Qty</th>
                            <th class="py-2.5 px-3 text-right">Rate</th>
                            <th class="py-2.5 px-3 text-right">Prev. Credited</th>
                            <th class="py-2.5 px-3 text-right">Remaining Eligible</th>
                            <th class="py-2.5 px-3 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <template x-for="item in searchResults" :key="item.shop_invoice_item_id">
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="py-2.5 px-3 whitespace-nowrap">
                                    <span class="font-mono font-bold text-slate-900 block" x-text="item.invoice_number"></span>
                                    <span class="text-[10px] text-slate-400" x-text="item.invoice_date"></span>
                                </td>
                                <td class="py-2.5 px-3">
                                    <span class="font-bold text-slate-900 block" x-text="item.product_name"></span>
                                    <span class="inline-flex items-center rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-700"
                                        x-text="'Grade ' + item.product_grade"></span>
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono" x-text="item.original_quantity + ' ' + item.unit"></td>
                                <td class="py-2.5 px-3 text-right font-mono" x-text="'₹' + Number(item.original_unit_price).toFixed(2)"></td>
                                <td class="py-2.5 px-3 text-right font-mono text-slate-500" x-text="item.previously_credited_quantity + ' ' + item.unit"></td>
                                <td class="py-2.5 px-3 text-right font-mono">
                                    <span class="font-bold" :class="item.remaining_eligible_quantity > 0 ? 'text-emerald-700' : 'text-slate-400'"
                                        x-text="item.remaining_eligible_quantity + ' ' + item.unit"></span>
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <template x-if="isLineAdded(item.shop_invoice_item_id)">
                                        <span class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 border border-emerald-200 px-2.5 py-1 text-[11px] font-bold text-emerald-700">
                                            <i data-lucide="check" class="w-3 h-3 text-emerald-600"></i> Added
                                        </span>
                                    </template>
                                    <template x-if="!isLineAdded(item.shop_invoice_item_id) && item.remaining_eligible_quantity > 0">
                                        <button type="button" @click="addLine(item)"
                                            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-bold text-slate-700 hover:bg-slate-50 hover:border-slate-300 shadow-2xs">
                                            <i data-lucide="plus" class="w-3 h-3 text-emerald-600"></i> + Add Line
                                        </button>
                                    </template>
                                    <template x-if="!isLineAdded(item.shop_invoice_item_id) && item.remaining_eligible_quantity <= 0">
                                        <span class="text-[10px] font-bold text-slate-400 uppercase">Fully Credited</span>
                                    </template>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <div x-show="searchResults.length === 0 && searchCompleted && !isSearching" x-cloak class="p-4 text-center text-xs text-slate-400 bg-slate-50 rounded-xl">
            No invoice lines found for the selected shop and search filters.
        </div>
    </div>

    <!-- SECTION 3: SELECTED CREDIT NOTE ADJUSTMENT LINES -->
    <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 font-black text-xs">
                    3
                </div>
                <div>
                    <h3 class="text-sm font-black text-slate-900">Credit Note Adjustment Lines</h3>
                    <p class="text-xs text-slate-500">Configure return disposition, editable credit rate, quantities, and reasons.</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-slate-600">Lines: <strong x-text="lines.length"></strong></span>
            </div>
        </div>

        <template x-if="lines.length === 0">
            <div class="py-10 text-center text-xs text-slate-400 space-y-2 bg-slate-50/50 rounded-2xl border border-dashed border-slate-200">
                <i data-lucide="package-search" class="w-8 h-8 mx-auto text-slate-300"></i>
                <p class="font-bold text-slate-600">No product adjustment lines selected yet</p>
                <p class="text-[11px] text-slate-400">Use the Historical Invoice Search above to find and add lines from previous invoices.</p>
            </div>
        </template>

        <template x-if="lines.length > 0">
            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 border-b border-slate-200 text-[10px] font-black uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="py-2.5 px-3">Source Invoice & Product</th>
                            <th class="py-2.5 px-3">Type</th>
                            <th class="py-2.5 px-3">Credit Qty</th>
                            <th class="py-2.5 px-3">Credit Rate (₹)</th>
                            <th class="py-2.5 px-3 text-right">Credit Amount</th>
                            <th class="py-2.5 px-3">Inventory Disposition</th>
                            <th class="py-2.5 px-3">Remarks</th>
                            <th class="py-2.5 px-3 text-center">✕</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <template x-for="(line, index) in lines" :key="line.shop_invoice_item_id">
                            <tr class="align-top hover:bg-slate-50/60 transition">
                                <!-- Source Invoice & Product (Read-only) -->
                                <td class="py-3 px-3 min-w-[200px]">
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-mono font-bold text-slate-900" x-text="line.invoice_number"></span>
                                            <span class="text-[10px] text-slate-400 font-mono" x-text="line.invoice_date"></span>
                                        </div>
                                        <div class="font-bold text-slate-800" x-text="line.product_name"></div>
                                        <div class="text-[10px] text-slate-500 flex items-center gap-2">
                                            <span>Orig Grade: <strong class="text-slate-800" x-text="line.product_grade"></strong></span>
                                            <span>&bull;</span>
                                            <span>Billed: <span class="font-mono" x-text="'₹' + Number(line.original_unit_price).toFixed(2)"></span></span>
                                        </div>
                                        <div class="text-[10px] text-slate-400">
                                            Eligible: <span class="font-bold text-emerald-700" x-text="line.remaining_quantity + ' ' + line.unit"></span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Adjustment Type Selector (RTN / DMG / ERR / GRD) -->
                                <td class="py-3 px-3 min-w-[120px]">
                                    <select x-model="line.adjustment_type" @change="onAdjustmentTypeChange(line)"
                                        class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs font-bold text-slate-800 focus:border-emerald-500">
                                        <option value="RTN">RTN — Physical Return</option>
                                        <option value="DMG">DMG — Shop Damage</option>
                                        <option value="GRD">GRD — Grade Downgrade</option>
                                        <option value="ERR">ERR — Rate/Qty Correction</option>
                                    </select>
                                    <span class="block text-[10px] text-slate-400 mt-1 uppercase" x-text="line.reason_type.replace('_', ' ')"></span>
                                </td>

                                <!-- Credit Quantity -->
                                <td class="py-3 px-3 min-w-[110px]">
                                    <div class="relative">
                                        <input type="number" step="0.001" min="0" :max="line.remaining_quantity"
                                            x-model.number="line.credit_quantity" @input="onCreditQuantityChange(line)"
                                            class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs font-mono font-bold text-slate-800 focus:border-emerald-500"
                                            :class="line.credit_quantity > line.remaining_quantity ? 'border-rose-300 bg-rose-50 text-rose-800' : ''">
                                    </div>
                                    <template x-if="line.credit_quantity > line.remaining_quantity">
                                        <p class="text-[10px] font-bold text-rose-600 mt-1">Exceeds remaining eligible quantity!</p>
                                    </template>
                                </td>

                                <!-- Editable Credit Rate (₹) -->
                                <td class="py-3 px-3 min-w-[110px]">
                                    <div class="relative">
                                        <span class="absolute left-2 top-1.5 text-xs text-slate-400 font-mono">₹</span>
                                        <input type="number" step="0.01" min="0"
                                            x-model.number="line.credit_rate" @input="onCreditRateChange(line)"
                                            class="w-full rounded-lg border border-slate-200 bg-white pl-5 pr-2 py-1.5 text-xs font-mono font-bold text-slate-800 focus:border-emerald-500">
                                    </div>
                                    <p class="text-[10px] text-slate-400 mt-1">Orig: ₹<span x-text="Number(line.original_unit_price).toFixed(2)"></span></p>
                                </td>

                                <!-- Calculated Amount (Preview) -->
                                <td class="py-3 px-3 min-w-[110px] text-right font-mono font-black text-emerald-700 text-sm">
                                    ₹<span x-text="Number(line.line_credit_amount).toFixed(2)"></span>
                                </td>

                                <!-- Inventory Disposition & Expected Grade / Damage Reason -->
                                <td class="py-3 px-3 min-w-[180px]">
                                    <div class="space-y-1.5">
                                        <!-- Inventory Action Selector -->
                                        <select x-model="line.inventory_action"
                                            class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px] font-bold text-slate-800 focus:border-emerald-500">
                                            <option value="return_to_stock">Return to Warehouse (Physical)</option>
                                            <option value="write_off_wastage">Damage at Shop (Write-Off)</option>
                                            <option value="none">No Inventory Movement (Financial Only)</option>
                                        </select>

                                        <!-- If Return to Stock: choose expected returned grade -->
                                        <template x-if="line.inventory_action === 'return_to_stock'">
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-[10px] font-bold text-slate-500 shrink-0">Exp Grade:</span>
                                                <select x-model="line.expected_returned_grade"
                                                    class="rounded border border-slate-200 bg-white px-1.5 py-0.5 text-[10px] font-bold text-slate-800">
                                                    <option value="A">Grade A</option>
                                                    <option value="B">Grade B</option>
                                                    <option value="C">Grade C</option>
                                                </select>
                                                <span class="text-[9px] text-slate-400">(Warehouse verifies on receipt)</span>
                                            </div>
                                        </template>

                                        <!-- If Damage Write-off: choose damage reason -->
                                        <template x-if="line.inventory_action === 'write_off_wastage'">
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-[10px] font-bold text-slate-500 shrink-0">Damage Reason:</span>
                                                <select x-model="line.damage_reason"
                                                    class="rounded border border-slate-200 bg-white px-1.5 py-0.5 text-[10px] font-bold text-slate-800">
                                                    @foreach ($wastageReasons as $wr)
                                                        <option value="{{ $wr->value }}">{{ $wr->label() }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </template>
                                    </div>
                                </td>

                                <!-- Remarks -->
                                <td class="py-3 px-3 min-w-[140px]">
                                    <input type="text" x-model="line.notes" placeholder="Optional line notes..."
                                        class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs text-slate-800 focus:border-emerald-500">
                                </td>

                                <!-- Delete Line -->
                                <td class="py-3 px-3 text-center">
                                    <button type="button" @click="removeLine(index)"
                                        class="h-7 w-7 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition flex items-center justify-center font-bold"
                                        title="Remove line">
                                        ✕
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </template>
    </div>

    <!-- SECTION 4: STANDALONE COMMERCIAL DISCOUNTS -->
    <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 font-black text-xs">
                    4
                </div>
                <div>
                    <h3 class="text-sm font-black text-slate-900">Commercial Discounts (Financial Only)</h3>
                    <p class="text-xs text-slate-500">Add commercial discounts or goodwill credits without selecting product lines.</p>
                </div>
            </div>
            <button type="button" @click="addDiscount()"
                class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50 shadow-2xs">
                <i data-lucide="plus" class="w-3.5 h-3.5 text-emerald-600"></i>
                <span>+ Add Commercial Discount</span>
            </button>
        </div>

        <template x-if="discounts.length === 0">
            <p class="text-xs text-slate-400 italic">No commercial discounts added. Click "+ Add Commercial Discount" if needed.</p>
        </template>

        <template x-if="discounts.length > 0">
            <div class="space-y-2">
                <template x-for="(disc, dIndex) in discounts" :key="dIndex">
                    <div class="flex flex-col sm:flex-row items-center gap-3 p-3 rounded-xl border border-slate-200 bg-slate-50/40">
                        <div class="w-full sm:w-48">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Discount Amount (₹)</label>
                            <input type="number" step="0.01" min="0.01" x-model.number="disc.line_credit_amount"
                                class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs font-mono font-bold text-slate-800 focus:border-emerald-500">
                        </div>
                        <div class="w-full flex-1">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Reason / Description</label>
                            <input type="text" x-model="disc.notes" placeholder="e.g. Additional volume discount / delayed loadout settlement"
                                class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs font-medium text-slate-800 focus:border-emerald-500">
                        </div>
                        <div class="w-full sm:w-auto pt-3 sm:pt-4">
                            <button type="button" @click="removeDiscount(dIndex)"
                                class="h-8 w-8 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition flex items-center justify-center font-bold"
                                title="Remove discount">
                                ✕
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>

    <!-- SECTION 5: LIVE DRAFT SUMMARY & ACTIONS (STICKY FOOTER CARD) -->
    <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-sm font-black text-slate-900">Draft Summary</h3>
                <span class="inline-flex items-center gap-1 text-[11px] font-bold text-amber-700">
                    <i data-lucide="info" class="w-3.5 h-3.5"></i>
                    <span>Preview Only — Verified and calculated by server on save. Zero ledger or inventory movements created until finalization.</span>
                </span>
            </div>
            <div class="text-right">
                <span class="text-[10px] font-bold uppercase text-slate-400 block">Total Credit Note</span>
                <span class="font-mono font-black text-2xl text-emerald-700">
                    -₹<span x-text="Number(grandTotal).toFixed(2)"></span>
                </span>
            </div>
        </div>

        <!-- Breakdown Chips -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-50/70 p-3.5 rounded-2xl border border-slate-100 text-xs">
            <div>
                <span class="block text-[10px] font-bold text-slate-400 uppercase">Return Credit (RTN)</span>
                <span class="font-mono font-bold text-slate-800 text-sm">
                    ₹<span x-text="Number(returnCreditTotal).toFixed(2)"></span>
                </span>
            </div>
            <div>
                <span class="block text-[10px] font-bold text-slate-400 uppercase">Damage Credit (DMG)</span>
                <span class="font-mono font-bold text-slate-800 text-sm">
                    ₹<span x-text="Number(damageCreditTotal).toFixed(2)"></span>
                </span>
            </div>
            <div>
                <span class="block text-[10px] font-bold text-slate-400 uppercase">Other Adjustments</span>
                <span class="font-mono font-bold text-slate-800 text-sm">
                    ₹<span x-text="Number(otherAdjustmentTotal).toFixed(2)"></span>
                </span>
            </div>
            <div>
                <span class="block text-[10px] font-bold text-slate-400 uppercase">Commercial Discounts</span>
                <span class="font-mono font-bold text-slate-800 text-sm">
                    ₹<span x-text="Number(commercialDiscountTotal).toFixed(2)"></span>
                </span>
            </div>
        </div>

        <!-- Action Bar -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-2">
            <div class="text-xs text-slate-500">
                <span x-text="distinctInvoiceCount"></span> invoice reference(s) &bull;
                <span x-text="lines.length + discounts.length"></span> total line(s)
            </div>

            <div class="flex items-center gap-3 w-full sm:w-auto">
                <a href="{{ route('admin.cashbook.reports.shops.credit-notes') }}"
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition shadow-2xs">
                    Cancel
                </a>

                <button type="button" @click="submitDraft()" :disabled="!canSave || isSubmitting"
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-6 py-2.5 text-xs font-bold text-white hover:bg-emerald-700 transition shadow-xs disabled:opacity-50">
                    <template x-if="isSubmitting">
                        <span class="inline-flex items-center gap-1.5">
                            <i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i>
                            <span>Saving Draft...</span>
                        </span>
                    </template>
                    <template x-if="!isSubmitting">
                        <span class="inline-flex items-center gap-1.5">
                            <i data-lucide="save" class="w-4 h-4"></i>
                            <span x-text="isEdit ? 'Update Draft' : 'Save Draft'"></span>
                        </span>
                    </template>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function shopCreditNoteEditor(config) {
        return {
            isEdit: config.isEdit || false,
            draftId: config.draftId || null,
            draftNumber: config.draftNumber || '',
            lockVersion: config.lockVersion || 1,
            expectedUpdatedAt: config.expectedUpdatedAt || '',
            shopId: config.selectedShopId || '',
            creditNoteDate: config.defaultDate || '{{ today()->toDateString() }}',
            notes: config.notes || '',
            searchLinesUrl: config.searchLinesUrl,
            saveUrl: config.saveUrl,
            redirectIndexUrl: config.redirectIndexUrl,

            lines: config.initialItems || [],
            discounts: config.initialDiscounts || [],

            // Search state
            searchQuery: '',
            searchMonth: '{{ now()->format('Y-m') }}',
            searchResults: [],
            isSearching: false,
            searchCompleted: false,

            // Save state
            isSubmitting: false,
            concurrencyConflict: false,

            init() {
                if (this.shopId && this.lines.length === 0) {
                    this.searchLines();
                }
            },

            onShopChange() {
                this.searchResults = [];
                this.searchCompleted = false;
                if (this.shopId) {
                    this.searchLines();
                }
            },

            searchLines() {
                if (!this.shopId) return;

                this.isSearching = true;
                this.searchCompleted = false;

                const url = new URL(this.searchLinesUrl, window.location.origin);
                url.searchParams.set('shop_id', this.shopId);
                if (this.searchMonth) {
                    url.searchParams.set('month', this.searchMonth);
                }
                if (this.searchQuery) {
                    url.searchParams.set('q', this.searchQuery);
                }
                if (this.draftId) {
                    url.searchParams.set('exclude_credit_note_id', this.draftId);
                }

                fetch(url.toString(), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    this.searchResults = data.items || [];
                    this.searchCompleted = true;
                    this.$nextTick(() => {
                        if (window.lucide) window.lucide.createIcons();
                    });
                })
                .catch(err => {
                    console.error('Search error:', err);
                    this.searchCompleted = true;
                })
                .finally(() => {
                    this.isSearching = false;
                });
            },

            isLineAdded(shopInvoiceItemId) {
                return this.lines.some(l => l.shop_invoice_item_id === shopInvoiceItemId);
            },

            addLine(item) {
                if (this.isLineAdded(item.shop_invoice_item_id)) return;

                const defaultRate = Number(item.original_unit_price) || 0;
                const eligibleQty = Number(item.remaining_eligible_quantity) || 0;

                this.lines.push({
                    source_shop_invoice_id: item.source_shop_invoice_id,
                    shop_invoice_item_id: item.shop_invoice_item_id,
                    invoice_number: item.invoice_number,
                    invoice_date: item.invoice_date,
                    product_id: item.product_id,
                    product_name: item.product_name,
                    product_grade: item.product_grade,
                    unit: item.unit,
                    original_quantity: Number(item.original_quantity),
                    original_unit_price: defaultRate,
                    original_line_total: Number(item.original_line_total),
                    remaining_quantity: eligibleQty,
                    credit_quantity: eligibleQty,
                    credit_rate: defaultRate,
                    line_credit_amount: Math.round(eligibleQty * defaultRate * 100) / 100,
                    adjustment_type: 'RTN',
                    reason_type: 'return_to_warehouse',
                    inventory_action: 'return_to_stock',
                    expected_returned_grade: item.product_grade,
                    damage_reason: 'transit_damage',
                    notes: ''
                });

                this.$nextTick(() => {
                    if (window.lucide) window.lucide.createIcons();
                });
            },

            removeLine(index) {
                this.lines.splice(index, 1);
            },

            onAdjustmentTypeChange(line) {
                if (line.adjustment_type === 'RTN') {
                    line.reason_type = 'return_to_warehouse';
                    line.inventory_action = 'return_to_stock';
                    line.expected_returned_grade = line.product_grade;
                } else if (line.adjustment_type === 'DMG') {
                    line.reason_type = 'damage_wastage';
                    line.inventory_action = 'write_off_wastage';
                    line.damage_reason = line.damage_reason || 'transit_damage';
                } else if (line.adjustment_type === 'GRD') {
                    line.reason_type = 'grade_downgrade';
                    line.inventory_action = 'return_to_stock';
                    line.expected_returned_grade = line.product_grade === 'A' ? 'B' : 'C';
                } else {
                    line.reason_type = 'price_correction';
                    line.inventory_action = 'none';
                }
            },

            onCreditQuantityChange(line) {
                const qty = Math.max(0, parseFloat(line.credit_quantity) || 0);
                const rate = Math.max(0, parseFloat(line.credit_rate) || 0);
                line.line_credit_amount = Math.round(qty * rate * 100) / 100;
            },

            onCreditRateChange(line) {
                const qty = Math.max(0, parseFloat(line.credit_quantity) || 0);
                const rate = Math.max(0, parseFloat(line.credit_rate) || 0);
                line.line_credit_amount = Math.round(qty * rate * 100) / 100;
            },

            addDiscount() {
                this.discounts.push({
                    line_credit_amount: 0.00,
                    notes: 'Commercial Discount',
                    source_shop_invoice_id: null
                });
            },

            removeDiscount(index) {
                this.discounts.splice(index, 1);
            },

            get returnCreditTotal() {
                let sum = 0;
                this.lines.forEach(l => {
                    if (l.inventory_action === 'return_to_stock') sum += (parseFloat(l.line_credit_amount) || 0);
                });
                return Math.round(sum * 100) / 100;
            },

            get damageCreditTotal() {
                let sum = 0;
                this.lines.forEach(l => {
                    if (l.inventory_action === 'write_off_wastage') sum += (parseFloat(l.line_credit_amount) || 0);
                });
                return Math.round(sum * 100) / 100;
            },

            get otherAdjustmentTotal() {
                let sum = 0;
                this.lines.forEach(l => {
                    if (l.inventory_action !== 'return_to_stock' && l.inventory_action !== 'write_off_wastage') {
                        sum += (parseFloat(l.line_credit_amount) || 0);
                    }
                });
                return Math.round(sum * 100) / 100;
            },

            get commercialDiscountTotal() {
                let sum = 0;
                this.discounts.forEach(d => {
                    sum += (parseFloat(d.line_credit_amount) || 0);
                });
                return Math.round(sum * 100) / 100;
            },

            get grandTotal() {
                return Math.round((this.returnCreditTotal + this.damageCreditTotal + this.otherAdjustmentTotal + this.commercialDiscountTotal) * 100) / 100;
            },

            get distinctInvoiceCount() {
                const set = new Set();
                this.lines.forEach(l => { if (l.source_shop_invoice_id) set.add(l.source_shop_invoice_id); });
                this.discounts.forEach(d => { if (d.source_shop_invoice_id) set.add(d.source_shop_invoice_id); });
                return set.size;
            },

            get canSave() {
                return this.shopId && (this.lines.length > 0 || this.discounts.length > 0) && this.grandTotal > 0 && !this.isSubmitting;
            },

            buildPayload() {
                const payload = {
                    shop_id: parseInt(this.shopId),
                    credit_note_date: this.creditNoteDate,
                    notes: this.notes,
                    expected_lock_version: this.lockVersion,
                    expected_updated_at: this.expectedUpdatedAt,
                    items: this.lines.map(l => ({
                        source_shop_invoice_id: parseInt(l.source_shop_invoice_id),
                        shop_invoice_item_id: parseInt(l.shop_invoice_item_id),
                        product_id: parseInt(l.product_id),
                        product_grade: l.product_grade,
                        expected_returned_grade: l.expected_returned_grade,
                        credit_quantity: parseFloat(l.credit_quantity) || 0,
                        credit_rate: parseFloat(l.credit_rate) || 0,
                        line_credit_amount: parseFloat(l.line_credit_amount) || 0,
                        reason_type: l.reason_type,
                        inventory_action: l.inventory_action,
                        damage_reason: l.damage_reason,
                        notes: l.notes
                    })),
                    discounts: this.discounts.map(d => ({
                        line_credit_amount: parseFloat(d.line_credit_amount) || 0,
                        notes: d.notes,
                        source_shop_invoice_id: d.source_shop_invoice_id ? parseInt(d.source_shop_invoice_id) : null
                    }))
                };
                return payload;
            },

            submitDraft() {
                if (!this.canSave) return;

                this.isSubmitting = true;
                this.concurrencyConflict = false;

                const payload = this.buildPayload();

                // Submit via fetch
                fetch(this.saveUrl, {
                    method: this.isEdit ? 'PUT' : 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(payload)
                })
                .then(async res => {
                    const data = await res.json();
                    if (res.status === 409) {
                        this.concurrencyConflict = true;
                        this.isSubmitting = false;
                        return;
                    }

                    if (!res.ok) {
                        const errorMsg = data.errors ? Object.values(data.errors).flat().join('\n') : (data.message || 'Validation error');
                        alert(errorMsg);
                        this.isSubmitting = false;
                        return;
                    }

                    if (data.redirect_url) {
                        window.location.href = data.redirect_url;
                    } else {
                        window.location.reload();
                    }
                })
                .catch(err => {
                    console.error('Submit error:', err);
                    // Fallback to standard form POST
                    const input = document.getElementById('payloadJsonInput');
                    input.value = JSON.stringify(payload);
                    document.getElementById('creditNoteFallbackForm').submit();
                });
            }
        };
    }

    if (window.Alpine) {
        window.Alpine.data('shopCreditNoteEditor', shopCreditNoteEditor);
    } else {
        document.addEventListener('alpine:init', function() {
            window.Alpine.data('shopCreditNoteEditor', shopCreditNoteEditor);
        });
    }
</script>
@endsection

