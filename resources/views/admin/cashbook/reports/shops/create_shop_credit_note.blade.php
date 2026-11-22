@extends('admin.cashbook.layouts.app')

@section('title', ($isEdit ? 'Edit Draft — ' . $creditNote->credit_note_number : 'Create Shop Credit Note') . ' — Green Leaf Cashbook')

@section('header_title')
    <i data-lucide="file-plus-2" class="w-5 h-5 text-emerald-600"></i>
    <span>{{ $isEdit ? 'Edit Shop Credit Note Draft' : 'Create Shop Credit Note' }}</span>
@endsection

@section('header_subtitle')
    Product-first daily credit adjustments, physical returns, damage write-offs, and commercial discounts.
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
        canOverrideBusinessDate: {{ $canOverrideBusinessDate ? 'true' : 'false' }},
        notes: '{{ $isEdit ? addslashes($creditNote->notes ?? '') : '' }}',
        searchProductsUrl: '{{ route('admin.cashbook.reports.shops.credit-notes.drafts.search-products') }}',
        dailyPriceUrl: '{{ route('admin.cashbook.reports.shops.credit-notes.drafts.daily-price') }}',
        searchLinesUrl: '{{ route('admin.cashbook.reports.shops.credit-notes.drafts.search-lines') }}',
        saveUrl: '{{ $isEdit ? route('admin.cashbook.reports.shops.credit-notes.drafts.update', $creditNote) : route('admin.cashbook.reports.shops.credit-notes.drafts.store') }}',
        finalizeUrl: '{{ $isEdit ? route('admin.cashbook.reports.shops.credit-notes.drafts.finalize', $creditNote) : '' }}',
        redirectIndexUrl: '{{ route('admin.cashbook.reports.shops.credit-notes') }}',
        initialItems: @js($isEdit ? $creditNote->items->where('line_type.value', 'product_adjustment')->values()->map(function ($it) {
            return [
                'source_shop_invoice_id' => $it->source_shop_invoice_id,
                'shop_invoice_item_id' => $it->shop_invoice_item_id,
                'invoice_number' => $it->sourceInvoice?->invoice_number ?? ($it->source_shop_invoice_id ? "INV-{$it->source_shop_invoice_id}" : null),
                'invoice_date' => $it->sourceInvoice?->business_date?->toDateString() ?? '',
                'product_id' => $it->product_id,
                'product_name' => $it->product?->name ?? $it->product_name,
                'product_grade' => $it->product_grade,
                'unit' => $it->price_unit ?: ($it->unit ?: 'kg'),
                'original_quantity' => (float) $it->original_quantity,
                'original_unit_price' => (float) $it->original_unit_price,
                'original_line_total' => (float) $it->original_line_total,
                'remaining_quantity' => (float) ($it->original_quantity ?: 999999),
                'credit_quantity' => (float) $it->adjusted_quantity,
                'credit_rate' => (float) ($it->credit_rate ?: $it->adjusted_unit_price),
                'line_credit_amount' => (float) $it->line_credit_amount,
                'direction' => $it->direction?->value ?? 'credit',
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

    <!-- SECTION 1: SHOP & BUSINESS DATE -->
    <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 font-black text-xs">
                    1
                </div>
                <h3 class="text-sm font-black text-slate-900">Shop & Business Date</h3>
            </div>
            <span class="text-xs font-medium text-slate-400">Select shop and effective adjustment date</span>
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
                @if ($canOverrideBusinessDate)
                    <input type="date" x-model="creditNoteDate" @change="onDateChange()"
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                    <p class="text-[10px] text-slate-400 mt-1">Admin date override authorized; must fall within open accounting period.</p>
                @else
                    <div class="relative">
                        <input type="date" x-model="creditNoteDate" readonly disabled
                            class="w-full rounded-xl border border-slate-200 bg-slate-100 px-3 py-2 text-xs font-bold text-slate-500 cursor-not-allowed">
                        <div class="absolute right-3 top-2.5 text-slate-400" title="Only authorized Admins can change business date">
                            <i data-lucide="lock" class="w-4 h-4"></i>
                        </div>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-1">Defaulted to today's date (Admin permission required to override).</p>
                @endif
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

    <!-- SECTION 2: ADD PRODUCT (PRODUCT-FIRST WORKFLOW) -->
    <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs space-y-4"
        :class="!shopId ? 'opacity-60 pointer-events-none' : ''">

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 font-black text-xs">
                    2
                </div>
                <div>
                    <h3 class="text-sm font-black text-slate-900">Add Product Adjustment</h3>
                    <p class="text-xs text-slate-500">Search product from catalog, enter adjustment details, and add to the list.</p>
                </div>
            </div>
            <template x-if="!shopId">
                <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-500">
                    Select a shop in Step 1 first
                </span>
            </template>
        </div>

        <!-- Product Search & Selection Box -->
        <div class="bg-slate-50/70 p-4 rounded-2xl border border-slate-100 space-y-4">
            <div class="relative">
                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                    Search Catalog Product <span class="text-rose-500">*</span>
                </label>
                <div class="relative">
                    <input type="text" x-model="productSearchQuery" @input.debounce.250ms="searchProducts()"
                        placeholder="Type product name or SKU (e.g. Onion, Tomato)..."
                        class="w-full rounded-xl border border-slate-200 bg-white pl-9 pr-10 py-2.5 text-xs font-bold text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 shadow-2xs">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-3"></i>
                    <template x-if="isSearchingProducts">
                        <i data-lucide="loader-2" class="w-4 h-4 text-emerald-600 animate-spin absolute right-3 top-3"></i>
                    </template>
                </div>

                <!-- Product Search Dropdown Results -->
                <div x-show="productSearchResults.length > 0" @click.away="productSearchResults = []" x-cloak
                    class="absolute z-20 mt-1 w-full rounded-2xl border border-slate-200 bg-white shadow-xl overflow-hidden max-h-60 overflow-y-auto divide-y divide-slate-100">
                    <template x-for="p in productSearchResults" :key="p.id">
                        <button type="button" @click="selectProduct(p)"
                            class="w-full text-left px-4 py-2.5 hover:bg-emerald-50/70 transition flex items-center justify-between group">
                            <div>
                                <span class="font-bold text-slate-900 group-hover:text-emerald-800 block text-xs" x-text="p.name"></span>
                                <span class="text-[10px] text-slate-400 font-mono" x-text="'SKU: ' + (p.sku || 'N/A') + ' • Unit: ' + p.unit"></span>
                            </div>
                            <span class="text-[11px] font-bold text-emerald-600 group-hover:underline">Select &rarr;</span>
                        </button>
                    </template>
                </div>
            </div>

            <!-- Active Selected Product Banner -->
            <template x-if="currentLine.product_id">
                <div class="flex items-center justify-between p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-xs">
                    <div class="flex items-center gap-2">
                        <i data-lucide="package-check" class="w-4 h-4 text-emerald-700 shrink-0"></i>
                        <div>
                            <span class="font-black text-emerald-950" x-text="currentLine.product_name"></span>
                            <span class="text-[11px] text-emerald-700 font-mono" x-text="'(Unit: ' + currentLine.unit + ')'"></span>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <template x-if="dailyPriceLoading">
                            <span class="text-[11px] text-slate-500 animate-pulse">Checking daily price...</span>
                        </template>
                        <template x-if="!dailyPriceLoading && dailyPriceInfo">
                            <span class="rounded bg-emerald-100 border border-emerald-300 px-2 py-0.5 text-[11px] font-bold text-emerald-900 font-mono">
                                Daily Price: ₹<span x-text="Number(dailyPriceInfo.price).toFixed(2)"></span> / <span x-text="currentLine.unit"></span>
                            </span>
                        </template>
                        <button type="button" @click="resetCurrentLine()" class="text-xs font-bold text-slate-400 hover:text-rose-600">
                            Change Product
                        </button>
                    </div>
                </div>
            </template>

            <!-- Credit Adjustment Details Form -->
            <template x-if="currentLine.product_id">
                <div class="space-y-4 pt-1">
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 items-start">
                        <!-- Grade -->
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 mb-1">Product Grade <span class="text-rose-500">*</span></label>
                            <select x-model="currentLine.product_grade" @change="fetchDailyPrice()"
                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-800 focus:border-emerald-500">
                                <option value="A">Grade A</option>
                                <option value="B">Grade B</option>
                                <option value="C">Grade C</option>
                            </select>
                        </div>

                        <!-- Quantity -->
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 mb-1">
                                Credit Quantity (<span x-text="currentLine.unit"></span>) <span class="text-rose-500">*</span>
                            </label>
                            <input type="number" step="0.001" min="0.001" x-model.number="currentLine.credit_quantity" @input="calculateCurrentLineAmount()"
                                placeholder="0.00"
                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-mono font-bold text-slate-800 focus:border-emerald-500">
                        </div>

                        <!-- Credit Rate -->
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 mb-1">
                                Credit Rate (₹) <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-2 text-xs text-slate-400 font-mono">₹</span>
                                <input type="number" step="0.01" min="0.01" x-model.number="currentLine.credit_rate" @input="calculateCurrentLineAmount()"
                                    placeholder="0.00"
                                    class="w-full rounded-xl border border-slate-200 bg-white pl-7 pr-3 py-2 text-xs font-mono font-bold text-slate-800 focus:border-emerald-500">
                            </div>
                        </div>

                        <!-- Live Calculated Amount Preview -->
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 mb-1">Credit Amount</label>
                            <div class="h-9 px-3 rounded-xl bg-emerald-50/80 border border-emerald-200 flex items-center justify-between font-mono">
                                <span class="text-[10px] font-bold uppercase text-emerald-800">Total</span>
                                <span class="font-black text-emerald-800 text-sm">
                                    ₹<span x-text="Number(currentLine.line_credit_amount).toFixed(2)"></span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 items-start">
                        <!-- Adjustment Type & Action -->
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 mb-1">Adjustment Disposition</label>
                            <select x-model="currentLine.adjustment_type" @change="onCurrentAdjustmentTypeChange()"
                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-800 focus:border-emerald-500">
                                <option value="RTN">Physical Return to Warehouse (Return to Stock)</option>
                                <option value="DMG">Shop Damage / Spoiled (Wastage Write-Off)</option>
                                <option value="ERR">Rate / Quantity Correction (Financial Only)</option>
                                <option value="CHG">Additional Charge (Shop Debit / Increases Payable)</option>
                            </select>
                        </div>

                        <!-- Expected Returned Grade (if RTN) OR Damage Reason (if DMG) -->
                        <div>
                            <template x-if="currentLine.inventory_action === 'return_to_stock'">
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Expected Return Grade</label>
                                    <select x-model="currentLine.expected_returned_grade"
                                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-800 focus:border-emerald-500">
                                        <option value="A">Grade A</option>
                                        <option value="B">Grade B</option>
                                        <option value="C">Grade C</option>
                                    </select>
                                    <p class="text-[9px] text-slate-400 mt-1">Verified physically by warehouse staff on receipt.</p>
                                </div>
                            </template>
                            <template x-if="currentLine.inventory_action === 'write_off_wastage'">
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Damage Reason</label>
                                    <select x-model="currentLine.damage_reason"
                                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-800 focus:border-emerald-500">
                                        @foreach ($wastageReasons as $wr)
                                            <option value="{{ $wr->value }}">{{ $wr->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </template>
                            <template x-if="currentLine.inventory_action === 'none'">
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-400 mb-1">Inventory Effect</label>
                                    <div class="h-9 px-3 rounded-xl bg-slate-100 flex items-center text-xs font-medium text-slate-500">
                                        Financial adjustment only (No stock movement)
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- Line Reason / Remarks -->
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 mb-1">Line Reason / Remarks</label>
                            <input type="text" x-model="currentLine.notes" placeholder="e.g. Bruised in transit / agreed concession"
                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 focus:border-emerald-500">
                        </div>
                    </div>

                    <!-- OPTIONAL HISTORICAL INVOICE LINKAGE (SECONDARY DRAWER) -->
                    <div class="pt-2 border-t border-slate-200/60">
                        <div class="flex items-center justify-between">
                            <button type="button" @click="toggleInvoiceSearch()"
                                class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-600 hover:text-emerald-700 transition">
                                <i data-lucide="link-2" class="w-3.5 h-3.5 text-slate-400"></i>
                                <span>Link Historical Invoice (Optional)</span>
                                <i data-lucide="chevron-down" class="w-3.5 h-3.5 transition-transform" :class="showInvoiceSearch ? 'rotate-180' : ''"></i>
                            </button>

                            <!-- If already linked -->
                            <template x-if="currentLine.source_shop_invoice_id">
                                <div class="inline-flex items-center gap-2 rounded-lg bg-indigo-50 border border-indigo-200 px-2.5 py-1 text-xs">
                                    <span class="font-bold text-indigo-900" x-text="'Linked: ' + currentLine.invoice_number"></span>
                                    <template x-if="currentLine.original_unit_price">
                                        <span class="text-[10px] text-indigo-700" x-text="'(Billed: ₹' + Number(currentLine.original_unit_price).toFixed(2) + ')'"></span>
                                    </template>
                                    <button type="button" @click="unlinkInvoiceFromCurrentLine()" class="text-indigo-500 hover:text-rose-600 font-bold ml-1">✕</button>
                                </div>
                            </template>
                        </div>

                        <!-- Collapsible Historical Invoice Finder -->
                        <div x-show="showInvoiceSearch" x-cloak class="mt-3 p-4 rounded-xl bg-white border border-slate-200 shadow-xs space-y-3">
                            <div class="flex items-center justify-between text-xs font-bold text-slate-700">
                                <span>Select Historical Invoice for Traceability & Comparison</span>
                                <span class="text-[11px] text-slate-400 font-normal">Optional reference only &bull; Does not restrict adjustment</span>
                            </div>

                            <div class="grid gap-2 sm:grid-cols-3 items-end">
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase">Invoice Month</label>
                                        <button type="button" x-show="searchMonth || searchQuery" @click="clearSearchFilters()" x-cloak
                                            class="text-[10px] font-bold text-emerald-700 hover:underline">
                                            Clear Filters
                                        </button>
                                    </div>
                                    <input type="month" x-model="searchMonth" @change="searchLines()"
                                        class="w-full rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-bold text-slate-800 focus:border-emerald-500">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Invoice # / Keyword</label>
                                    <input type="text" x-model="searchQuery" @keydown.enter.prevent="searchLines()" placeholder="Search invoice..."
                                        class="w-full rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-800 focus:border-emerald-500">
                                </div>
                                <div>
                                    <button type="button" @click="searchLines()" :disabled="isSearching"
                                        class="w-full rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-bold text-white hover:bg-slate-900 transition flex items-center justify-center gap-1">
                                        <i data-lucide="search" class="w-3.5 h-3.5"></i>
                                        <span>Find Invoices</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Invoice Search Results -->
                            <div x-show="searchResults.length > 0" class="max-h-48 overflow-y-auto rounded-lg border border-slate-200">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-slate-50 text-[10px] font-black uppercase text-slate-500 border-b border-slate-200">
                                        <tr>
                                            <th class="py-1.5 px-2">Invoice #</th>
                                            <th class="py-1.5 px-2">Date</th>
                                            <th class="py-1.5 px-2">Product / Grade</th>
                                            <th class="py-1.5 px-2 text-right">Billed Qty</th>
                                            <th class="py-1.5 px-2 text-right">Billed Rate</th>
                                            <th class="py-1.5 px-2 text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <template x-for="item in searchResults" :key="item.shop_invoice_item_id">
                                            <tr class="hover:bg-slate-50 transition">
                                                <td class="py-1.5 px-2 font-mono font-bold text-slate-900" x-text="item.invoice_number"></td>
                                                <td class="py-1.5 px-2 text-slate-500" x-text="item.invoice_date"></td>
                                                <td class="py-1.5 px-2">
                                                    <span class="font-bold text-slate-800" x-text="item.product_name"></span>
                                                    <span class="text-[10px] text-slate-400" x-text="'(' + item.product_grade + ')'"></span>
                                                </td>
                                                <td class="py-1.5 px-2 text-right font-mono" x-text="item.original_quantity + ' ' + item.unit"></td>
                                                <td class="py-1.5 px-2 text-right font-mono" x-text="'₹' + Number(item.original_unit_price).toFixed(2)"></td>
                                                <td class="py-1.5 px-2 text-center">
                                                    <button type="button" @click="linkInvoiceToCurrentLine(item)"
                                                        class="rounded bg-emerald-600 px-2 py-0.5 text-[11px] font-bold text-white hover:bg-emerald-700">
                                                        Link
                                                    </button>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>

                            <div x-show="searchResults.length === 0 && searchCompleted && !isSearching" class="text-center py-2 text-xs text-slate-400 italic">
                                No matching invoice lines found.
                            </div>
                        </div>
                    </div>

                    <!-- Add Button -->
                    <div class="flex items-center justify-end pt-2">
                        <button type="button" @click="addProductLine()"
                            class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-bold text-white hover:bg-emerald-700 transition shadow-xs">
                            <i data-lucide="plus-circle" class="w-4 h-4"></i>
                            <span>+ Add Product to Credit Note</span>
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- SECTION 3: ADDED PRODUCT ADJUSTMENT LINES -->
    <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 font-black text-xs">
                    3
                </div>
                <div>
                    <h3 class="text-sm font-black text-slate-900">Credit Note Products</h3>
                    <p class="text-xs text-slate-500">Review added products, editable quantities, rates, and disposition.</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-slate-600">Products Added: <strong x-text="lines.length"></strong></span>
            </div>
        </div>

        <template x-if="lines.length === 0">
            <div class="py-10 text-center text-xs text-slate-400 space-y-2 bg-slate-50/50 rounded-2xl border border-dashed border-slate-200">
                <i data-lucide="shopping-bag" class="w-8 h-8 mx-auto text-slate-300"></i>
                <p class="font-bold text-slate-600">No products added yet</p>
                <p class="text-[11px] text-slate-400">Search and select a catalog product above to add items to this Credit Note.</p>
            </div>
        </template>

        <template x-if="lines.length > 0">
            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 border-b border-slate-200 text-[10px] font-black uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="py-2.5 px-3">Product & Traceability</th>
                            <th class="py-2.5 px-3">Disposition / Type</th>
                            <th class="py-2.5 px-3">Grade</th>
                            <th class="py-2.5 px-3 text-right">Quantity</th>
                            <th class="py-2.5 px-3 text-right">Rate (₹)</th>
                            <th class="py-2.5 px-3 text-right">Credit Amount</th>
                            <th class="py-2.5 px-3">Remarks</th>
                            <th class="py-2.5 px-3 text-center">✕</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <template x-for="(line, index) in lines" :key="index">
                            <tr class="align-top hover:bg-slate-50/60 transition">
                                <!-- Product Name & Optional Invoice -->
                                <td class="py-3 px-3 min-w-[200px]">
                                    <div class="font-bold text-slate-900" x-text="line.product_name"></div>
                                    <span class="text-[10px] text-slate-400 font-mono" x-text="'Unit: ' + line.unit"></span>
                                    <template x-if="line.source_shop_invoice_id">
                                        <div class="mt-1 flex items-center gap-1 text-[10px] text-indigo-700 bg-indigo-50 px-1.5 py-0.5 rounded w-fit font-mono font-bold">
                                            <i data-lucide="link" class="w-3 h-3"></i>
                                            <span x-text="line.invoice_number || ('INV #' + line.source_shop_invoice_id)"></span>
                                            <template x-if="line.original_unit_price">
                                                <span class="text-indigo-500 font-normal" x-text="'(Billed: ₹' + Number(line.original_unit_price).toFixed(2) + ')'"></span>
                                            </template>
                                        </div>
                                    </template>
                                    <template x-if="!line.source_shop_invoice_id">
                                        <span class="block text-[10px] text-slate-400 mt-0.5">Catalog Direct (No Invoice Link)</span>
                                    </template>
                                </td>

                                <!-- Disposition & Direction -->
                                <td class="py-3 px-3 min-w-[140px]">
                                    <span class="inline-flex items-center rounded-lg px-2 py-0.5 text-[11px] font-bold"
                                        :class="{
                                            'bg-emerald-100 text-emerald-800': line.inventory_action === 'return_to_stock',
                                            'bg-amber-100 text-amber-800': line.inventory_action === 'write_off_wastage',
                                            'bg-slate-100 text-slate-700': line.inventory_action === 'none' && line.direction === 'credit',
                                            'bg-rose-100 text-rose-800': line.direction === 'debit'
                                        }">
                                        <span x-text="line.direction === 'debit' ? 'Additional Charge (Debit)' : (line.inventory_action === 'return_to_stock' ? 'Physical Return' : (line.inventory_action === 'write_off_wastage' ? 'Damage Write-Off' : 'Financial Only'))"></span>
                                    </span>
                                </td>

                                <!-- Grade -->
                                <td class="py-3 px-3">
                                    <select x-model="line.product_grade"
                                        class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-bold text-slate-800">
                                        <option value="A">Grade A</option>
                                        <option value="B">Grade B</option>
                                        <option value="C">Grade C</option>
                                    </select>
                                </td>

                                <!-- Quantity -->
                                <td class="py-3 px-3 min-w-[100px] text-right">
                                    <input type="number" step="0.001" min="0.001"
                                        x-model.number="line.credit_quantity" @input="onLineQuantityChange(line)"
                                        class="w-24 rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-mono font-bold text-right text-slate-800 focus:border-emerald-500">
                                </td>

                                <!-- Rate (₹) -->
                                <td class="py-3 px-3 min-w-[100px] text-right">
                                    <input type="number" step="0.01" min="0.01"
                                        x-model.number="line.credit_rate" @input="onLineRateChange(line)"
                                        class="w-24 rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-mono font-bold text-right text-slate-800 focus:border-emerald-500">
                                </td>

                                <!-- Calculated Amount -->
                                <td class="py-3 px-3 min-w-[110px] text-right font-mono font-black text-sm"
                                    :class="line.direction === 'debit' ? 'text-rose-700' : 'text-emerald-700'">
                                    <span x-text="line.direction === 'debit' ? '+₹' : '-₹'"></span><span x-text="Number(line.line_credit_amount).toFixed(2)"></span>
                                </td>

                                <!-- Remarks -->
                                <td class="py-3 px-3 min-w-[140px]">
                                    <input type="text" x-model="line.notes" placeholder="Notes..."
                                        class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs text-slate-800 focus:border-emerald-500">
                                </td>

                                <!-- Delete Line -->
                                <td class="py-3 px-3 text-center">
                                    <button type="button" @click="removeLine(index)"
                                        class="h-7 w-7 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition flex items-center justify-center font-bold"
                                        title="Remove product">
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

    <!-- SECTION 4: STANDALONE COMMERCIAL DISCOUNTS (OPTIONAL) -->
    <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 font-black text-xs">
                    4
                </div>
                <div>
                    <h3 class="text-sm font-black text-slate-900">Commercial Discounts (Financial Only)</h3>
                    <p class="text-xs text-slate-500">Add lump-sum commercial discounts or settlement allowances without selecting product lines.</p>
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
                <h3 class="text-sm font-black text-slate-900">Credit Note Summary</h3>
                <span class="inline-flex items-center gap-1 text-[11px] font-bold text-amber-700">
                    <i data-lucide="info" class="w-3.5 h-3.5"></i>
                    <span>Preview Only — Verified and calculated by server on save. Zero ledger or inventory movements created until finalization.</span>
                </span>
            </div>
            <div class="text-right">
                <span class="text-[10px] font-bold uppercase text-slate-400 block">Total Credit Adjustment</span>
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

                <button type="button" @click="openFinalizeModal()" :disabled="!canSave || isSubmitting"
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-xs font-bold text-white hover:bg-indigo-700 transition shadow-xs disabled:opacity-50">
                    <i data-lucide="check-check" class="w-4 h-4 text-white"></i>
                    <span>Save & Finalize</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Finalization Confirmation Modal -->
    <div x-show="showFinalizeModal" x-cloak
        class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="showFinalizeModal = false"
            class="relative w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl space-y-5 border border-slate-200">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 font-bold">
                        <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-slate-900">Confirm Credit Note Finalization</h3>
                        <p class="text-xs text-slate-500">Post official financial credit to shop account</p>
                    </div>
                </div>
                <button type="button" @click="showFinalizeModal = false"
                    class="h-8 w-8 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 flex items-center justify-center font-bold">✕</button>
            </div>

            <!-- Summary in modal -->
            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 space-y-2 text-xs">
                <div class="flex justify-between">
                    <span class="text-slate-500">Effective Business Date:</span>
                    <span class="font-bold text-slate-800" x-text="creditNoteDate"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Total Financial Credit:</span>
                    <span class="font-mono font-black text-emerald-700 text-base">-₹<span x-text="Number(grandTotal).toFixed(2)"></span></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Products & Adjustments:</span>
                    <span class="font-bold text-slate-800"><span x-text="lines.length + discounts.length"></span> line(s)</span>
                </div>
            </div>

            <!-- Explicit warning -->
            <div class="p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-900 space-y-1">
                <div class="font-bold flex items-center gap-1.5 text-amber-950">
                    <i data-lucide="shield-alert" class="w-4 h-4 text-amber-600 shrink-0"></i>
                    <span>Irreversible Financial Posting</span>
                </div>
                <p>Finalizing will assign an official Credit Note number and immediately post a financial credit entry to the shop's cashbook ledger on the selected business date. Once finalized, the draft cannot be edited.</p>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-600 mb-1">Finalization Remark (Optional)</label>
                <input type="text" x-model="finalizationNote" placeholder="e.g. Approved by Office Accounting on audit"
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-800 focus:border-indigo-500">
            </div>

            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100">
                <button type="button" @click="showFinalizeModal = false"
                    class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">
                    Cancel
                </button>
                <button type="button" @click="executeFinalize()" :disabled="isSubmitting"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-5 py-2 text-xs font-bold text-white hover:bg-indigo-700 transition shadow-xs disabled:opacity-50">
                    <template x-if="isSubmitting">
                        <span class="inline-flex items-center gap-1">
                            <i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin"></i>
                            <span>Posting Credit...</span>
                        </span>
                    </template>
                    <template x-if="!isSubmitting">
                        <span class="inline-flex items-center gap-1">
                            <i data-lucide="check" class="w-3.5 h-3.5"></i>
                            <span>Confirm & Post Credit</span>
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
            canOverrideBusinessDate: config.canOverrideBusinessDate || false,
            notes: config.notes || '',
            searchProductsUrl: config.searchProductsUrl,
            dailyPriceUrl: config.dailyPriceUrl,
            searchLinesUrl: config.searchLinesUrl,
            saveUrl: config.saveUrl,
            finalizeUrl: config.finalizeUrl || '',
            redirectIndexUrl: config.redirectIndexUrl,

            lines: config.initialItems || [],
            discounts: config.initialDiscounts || [],

            // Catalog product search state
            productSearchQuery: '',
            productSearchResults: [],
            isSearchingProducts: false,
            selectedProduct: null,
            dailyPriceLoading: false,
            dailyPriceInfo: null,

            // Current line in progress
            currentLine: {
                product_id: null,
                product_name: '',
                unit: 'kg',
                product_grade: 'A',
                credit_quantity: '',
                credit_rate: '',
                line_credit_amount: 0,
                direction: 'credit',
                adjustment_type: 'RTN',
                reason_type: 'return_to_warehouse',
                inventory_action: 'return_to_stock',
                expected_returned_grade: 'A',
                damage_reason: 'transit_damage',
                notes: '',
                source_shop_invoice_id: null,
                shop_invoice_item_id: null,
                invoice_number: null,
                invoice_date: null,
                original_quantity: null,
                original_unit_price: null,
                original_line_total: null
            },

            // Optional Historical Invoice Search state
            showInvoiceSearch: false,
            searchQuery: '',
            searchMonth: '',
            searchResults: [],
            searchError: '',
            isSearching: false,
            searchCompleted: false,

            // Save state
            isSubmitting: false,
            concurrencyConflict: false,
            showFinalizeModal: false,
            finalizationNote: '',

            init() {
                // Initialize icons
                this.$nextTick(() => {
                    if (window.lucide) window.lucide.createIcons();
                });
            },

            onShopChange() {
                this.resetCurrentLine();
                this.searchResults = [];
                this.searchError = '';
                this.searchCompleted = false;
            },

            onDateChange() {
                if (this.currentLine.product_id) {
                    this.fetchDailyPrice();
                }
            },

            searchProducts() {
                const q = (this.productSearchQuery || '').trim();
                if (q.length < 1) {
                    this.productSearchResults = [];
                    return;
                }
                if (!this.shopId) return;

                this.isSearchingProducts = true;
                const url = new URL(this.searchProductsUrl, window.location.origin);
                url.searchParams.set('shop_id', this.shopId);
                url.searchParams.set('q', q);

                fetch(url.toString(), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    this.productSearchResults = data.items || [];
                })
                .catch(err => {
                    console.error('Product search error:', err);
                    this.productSearchResults = [];
                })
                .finally(() => {
                    this.isSearchingProducts = false;
                });
            },

            selectProduct(p) {
                this.selectedProduct = p;
                this.currentLine.product_id = p.id;
                this.currentLine.product_name = p.name;
                this.currentLine.unit = p.unit || 'kg';
                this.currentLine.product_grade = 'A';
                this.productSearchResults = [];
                this.productSearchQuery = p.name;
                this.fetchDailyPrice();
            },

            fetchDailyPrice() {
                if (!this.shopId || !this.currentLine.product_id) return;

                this.dailyPriceLoading = true;
                const url = new URL(this.dailyPriceUrl, window.location.origin);
                url.searchParams.set('shop_id', this.shopId);
                url.searchParams.set('product_id', this.currentLine.product_id);
                url.searchParams.set('grade', this.currentLine.product_grade || 'A');
                url.searchParams.set('business_date', this.creditNoteDate || '{{ today()->toDateString() }}');

                fetch(url.toString(), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.available && data.price) {
                        this.dailyPriceInfo = data;
                        if (!this.currentLine.credit_rate || parseFloat(this.currentLine.credit_rate) === 0) {
                            this.currentLine.credit_rate = data.price;
                            this.calculateCurrentLineAmount();
                        }
                    } else {
                        this.dailyPriceInfo = null;
                    }
                })
                .catch(() => {
                    this.dailyPriceInfo = null;
                })
                .finally(() => {
                    this.dailyPriceLoading = false;
                });
            },

            calculateCurrentLineAmount() {
                const qty = Math.max(0, parseFloat(this.currentLine.credit_quantity) || 0);
                const rate = Math.max(0, parseFloat(this.currentLine.credit_rate) || 0);
                this.currentLine.line_credit_amount = Math.round(qty * rate * 100) / 100;
            },

            onCurrentAdjustmentTypeChange() {
                if (this.currentLine.adjustment_type === 'RTN') {
                    this.currentLine.direction = 'credit';
                    this.currentLine.reason_type = 'return_to_warehouse';
                    this.currentLine.inventory_action = 'return_to_stock';
                    this.currentLine.expected_returned_grade = this.currentLine.product_grade || 'A';
                } else if (this.currentLine.adjustment_type === 'DMG') {
                    this.currentLine.direction = 'credit';
                    this.currentLine.reason_type = 'damage_wastage';
                    this.currentLine.inventory_action = 'write_off_wastage';
                    this.currentLine.damage_reason = this.currentLine.damage_reason || 'transit_damage';
                } else if (this.currentLine.adjustment_type === 'CHG') {
                    this.currentLine.direction = 'debit';
                    this.currentLine.reason_type = 'price_correction';
                    this.currentLine.inventory_action = 'none';
                } else {
                    this.currentLine.direction = 'credit';
                    this.currentLine.reason_type = 'price_correction';
                    this.currentLine.inventory_action = 'none';
                }
            },

            toggleInvoiceSearch() {
                this.showInvoiceSearch = !this.showInvoiceSearch;
                if (this.showInvoiceSearch && this.searchResults.length === 0 && this.shopId) {
                    this.searchLines();
                }
                this.$nextTick(() => {
                    if (window.lucide) window.lucide.createIcons();
                });
            },

            clearSearchFilters() {
                this.searchMonth = '';
                this.searchQuery = '';
                this.searchError = '';
                if (this.shopId) {
                    this.searchLines();
                }
            },

            searchLines() {
                if (!this.shopId) return;

                this.isSearching = true;
                this.searchError = '';
                this.searchCompleted = false;

                const url = new URL(this.searchLinesUrl, window.location.origin);
                url.searchParams.set('shop_id', this.shopId);
                if (this.searchMonth) {
                    url.searchParams.set('month', this.searchMonth);
                }
                if (this.searchQuery) {
                    url.searchParams.set('q', this.searchQuery);
                } else if (this.currentLine.product_name) {
                    url.searchParams.set('q', this.currentLine.product_name);
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
                .then(async res => {
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        throw new Error(data.message || 'Failed to load invoice lines.');
                    }
                    return data;
                })
                .then(data => {
                    this.searchResults = data.items || [];
                    this.searchCompleted = true;
                })
                .catch(err => {
                    console.error('Search error:', err);
                    this.searchResults = [];
                    this.searchError = err.message || 'Failed to load invoice lines.';
                    this.searchCompleted = true;
                })
                .finally(() => {
                    this.isSearching = false;
                });
            },

            linkInvoiceToCurrentLine(item) {
                this.currentLine.source_shop_invoice_id = item.source_shop_invoice_id;
                this.currentLine.shop_invoice_item_id = item.shop_invoice_item_id;
                this.currentLine.invoice_number = item.invoice_number;
                this.currentLine.invoice_date = item.invoice_date;
                this.currentLine.original_quantity = item.original_quantity;
                this.currentLine.original_unit_price = item.original_unit_price;
                this.currentLine.original_line_total = item.original_line_total;

                if (!this.currentLine.credit_rate || parseFloat(this.currentLine.credit_rate) === 0) {
                    this.currentLine.credit_rate = item.original_unit_price;
                }
                if (!this.currentLine.credit_quantity || parseFloat(this.currentLine.credit_quantity) === 0) {
                    this.currentLine.credit_quantity = item.remaining_eligible_quantity || item.original_quantity;
                }
                this.calculateCurrentLineAmount();
                this.showInvoiceSearch = false;
            },

            unlinkInvoiceFromCurrentLine() {
                this.currentLine.source_shop_invoice_id = null;
                this.currentLine.shop_invoice_item_id = null;
                this.currentLine.invoice_number = null;
                this.currentLine.invoice_date = null;
                this.currentLine.original_quantity = null;
                this.currentLine.original_unit_price = null;
                this.currentLine.original_line_total = null;
            },

            addProductLine() {
                if (!this.currentLine.product_id) {
                    alert('Please search and select a product first.');
                    return;
                }
                const qty = parseFloat(this.currentLine.credit_quantity);
                if (isNaN(qty) || qty <= 0) {
                    alert('Please enter a valid credit quantity greater than 0.');
                    return;
                }
                const rate = parseFloat(this.currentLine.credit_rate);
                if (isNaN(rate) || rate <= 0) {
                    alert('Please enter a valid credit rate greater than 0.');
                    return;
                }

                const amt = Math.round(qty * rate * 100) / 100;

                this.lines.push({
                    source_shop_invoice_id: this.currentLine.source_shop_invoice_id || null,
                    shop_invoice_item_id: this.currentLine.shop_invoice_item_id || null,
                    invoice_number: this.currentLine.invoice_number || null,
                    invoice_date: this.currentLine.invoice_date || null,
                    original_quantity: this.currentLine.original_quantity || 0,
                    original_unit_price: this.currentLine.original_unit_price || 0,
                    original_line_total: this.currentLine.original_line_total || 0,
                    product_id: this.currentLine.product_id,
                    product_name: this.currentLine.product_name,
                    product_grade: this.currentLine.product_grade || 'A',
                    unit: this.currentLine.unit || 'kg',
                    credit_quantity: qty,
                    credit_rate: rate,
                    line_credit_amount: amt,
                    direction: this.currentLine.direction || 'credit',
                    adjustment_type: this.currentLine.adjustment_type || 'RTN',
                    reason_type: this.currentLine.reason_type || 'return_to_warehouse',
                    inventory_action: this.currentLine.inventory_action || 'return_to_stock',
                    expected_returned_grade: this.currentLine.expected_returned_grade || (this.currentLine.product_grade || 'A'),
                    damage_reason: this.currentLine.damage_reason || 'transit_damage',
                    notes: this.currentLine.notes || ''
                });

                this.resetCurrentLine();

                this.$nextTick(() => {
                    if (window.lucide) window.lucide.createIcons();
                });
            },

            resetCurrentLine() {
                this.selectedProduct = null;
                this.productSearchQuery = '';
                this.dailyPriceInfo = null;
                this.showInvoiceSearch = false;
                this.currentLine = {
                    product_id: null,
                    product_name: '',
                    unit: 'kg',
                    product_grade: 'A',
                    credit_quantity: '',
                    credit_rate: '',
                    line_credit_amount: 0,
                    direction: 'credit',
                    adjustment_type: 'RTN',
                    reason_type: 'return_to_warehouse',
                    inventory_action: 'return_to_stock',
                    expected_returned_grade: 'A',
                    damage_reason: 'transit_damage',
                    notes: '',
                    source_shop_invoice_id: null,
                    shop_invoice_item_id: null,
                    invoice_number: null,
                    invoice_date: null,
                    original_quantity: null,
                    original_unit_price: null,
                    original_line_total: null
                };
            },

            removeLine(index) {
                this.lines.splice(index, 1);
            },

            onLineQuantityChange(line) {
                const qty = Math.max(0, parseFloat(line.credit_quantity) || 0);
                const rate = Math.max(0, parseFloat(line.credit_rate) || 0);
                line.line_credit_amount = Math.round(qty * rate * 100) / 100;
            },

            onLineRateChange(line) {
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
                    if (l.inventory_action === 'return_to_stock' && l.direction === 'credit') {
                        sum += (parseFloat(l.line_credit_amount) || 0);
                    }
                });
                return Math.round(sum * 100) / 100;
            },

            get damageCreditTotal() {
                let sum = 0;
                this.lines.forEach(l => {
                    if (l.inventory_action === 'write_off_wastage' && l.direction === 'credit') {
                        sum += (parseFloat(l.line_credit_amount) || 0);
                    }
                });
                return Math.round(sum * 100) / 100;
            },

            get otherAdjustmentTotal() {
                let sum = 0;
                this.lines.forEach(l => {
                    if (l.inventory_action !== 'return_to_stock' && l.inventory_action !== 'write_off_wastage') {
                        const amt = (parseFloat(l.line_credit_amount) || 0);
                        sum += (l.direction === 'debit' ? -amt : amt);
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
                return this.shopId && (this.lines.length > 0 || this.discounts.length > 0) && this.grandTotal !== 0 && !this.isSubmitting;
            },

            buildPayload() {
                const payload = {
                    shop_id: parseInt(this.shopId),
                    credit_note_date: this.creditNoteDate,
                    notes: this.notes,
                    expected_lock_version: this.lockVersion,
                    expected_updated_at: this.expectedUpdatedAt,
                    items: this.lines.map(l => ({
                        source_shop_invoice_id: l.source_shop_invoice_id ? parseInt(l.source_shop_invoice_id) : null,
                        shop_invoice_item_id: l.shop_invoice_item_id ? parseInt(l.shop_invoice_item_id) : null,
                        product_id: parseInt(l.product_id),
                        product_name: l.product_name,
                        product_grade: l.product_grade,
                        unit: l.unit,
                        direction: l.direction || 'credit',
                        expected_returned_grade: l.expected_returned_grade || l.product_grade,
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
                    const input = document.getElementById('payloadJsonInput');
                    input.value = JSON.stringify(payload);
                    document.getElementById('creditNoteFallbackForm').submit();
                });
            },

            openFinalizeModal() {
                if (!this.canSave) return;
                this.showFinalizeModal = true;
                this.$nextTick(() => {
                    if (window.lucide) window.lucide.createIcons();
                });
            },

            executeFinalize() {
                if (!this.canSave || this.isSubmitting) return;

                this.isSubmitting = true;
                this.concurrencyConflict = false;

                const payload = this.buildPayload();

                // Step 1: Persist draft changes
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
                        this.showFinalizeModal = false;
                        this.isSubmitting = false;
                        return null;
                    }

                    if (!res.ok) {
                        const errorMsg = data.errors ? Object.values(data.errors).flat().join('\n') : (data.message || 'Validation error');
                        alert(errorMsg);
                        this.isSubmitting = false;
                        return null;
                    }

                    const targetDraftKey = this.isEdit ? (this.draftNumber || this.draftId) : (data.credit_note_number || data.credit_note_id || null);
                    if (!targetDraftKey) {
                        alert('Could not determine draft to finalize.');
                        this.isSubmitting = false;
                        return null;
                    }

                    // Step 2: Post finalization request
                    const finalizeUrl = data.finalize_url || this.finalizeUrl || `/admin/cashbook/reports/shops/credit-notes/drafts/${encodeURIComponent(targetDraftKey)}/finalize`;
                    return fetch(finalizeUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            notes: this.finalizationNote || ''
                        })
                    });
                })
                .then(async res => {
                    if (!res) return;
                    const finalizeData = await res.json();
                    if (!res.ok) {
                        alert(finalizeData.message || 'Failed to finalize credit note');
                        this.isSubmitting = false;
                        return;
                    }

                    if (finalizeData.redirect_url) {
                        window.location.href = finalizeData.redirect_url;
                    } else {
                        window.location.href = '{{ route('admin.cashbook.reports.shops.credit-notes') }}';
                    }
                })
                .catch(err => {
                    console.error('Finalization error:', err);
                    alert('An unexpected network error occurred during finalization.');
                    this.isSubmitting = false;
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
