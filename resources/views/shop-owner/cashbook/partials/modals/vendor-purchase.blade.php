{{-- VENDOR PURCHASE MODAL --}}
<div id="vendor-purchase-modal" onclick="handleModalBackdropClick(event, 'vendor-purchase-modal')"
     class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center bg-slate-900/50 backdrop-blur-xs hidden transition-all duration-200 pb-[calc(env(safe-area-inset-bottom,0px)+5.25rem)] sm:pb-0 px-0 sm:px-4">
    <div onclick="event.stopPropagation()"
         class="w-full max-w-xl rounded-t-2xl sm:rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-2xl flex flex-col max-h-[82vh] sm:max-h-[88vh]">

        {{-- Header --}}
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 gap-2 shrink-0">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-8 h-8 rounded-xl bg-emerald-600 flex items-center justify-center text-white shrink-0 shadow-2xs">
                    <i data-lucide="shopping-bag" class="h-4 w-4"></i>
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <h3 class="text-xs sm:text-sm font-black uppercase tracking-wider text-slate-900 truncate" id="vp-modal-title">
                            VENDOR PURCHASE
                        </h3>
                        <span id="vp-category-badge" class="hidden rounded bg-emerald-100 text-emerald-800 text-[10px] font-black px-2 py-0.5 border border-emerald-200"></span>
                        <span id="vp-settlement-badge" class="hidden rounded bg-slate-100 text-slate-700 text-[10px] font-bold px-2 py-0.5 border border-slate-200"></span>
                    </div>
                    <p class="text-[11px] font-bold text-slate-400 truncate mt-0.5">
                        {{ $shop->name }} &bull; <span id="vp-date-display">{{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }}</span>
                    </p>
                </div>
            </div>
            <button type="button" aria-label="Close" onclick="closeVendorPurchaseModal()"
                    class="h-8 w-8 min-h-[32px] min-w-[32px] inline-flex items-center justify-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 hover:text-slate-900 active:scale-95 transition cursor-pointer shrink-0">
                <i data-lucide="x" class="h-4 w-4"></i>
            </button>
        </div>

        {{-- Scrollable Form Body --}}
        <form id="vendor-purchase-form" onsubmit="event.preventDefault(); submitVendorPurchase();" class="flex-1 overflow-y-auto pt-3.5 pb-2 space-y-3.5 pr-0.5">
            {{-- Category Context Hidden Field --}}
            <input type="hidden" id="vp-category-id" name="shop_ledger_entry_setting_id" value="">

            {{-- Validation / Server Error Container --}}
            <div id="vp-error-alert" class="rounded-xl border border-rose-200 bg-rose-50/90 p-3 text-xs font-bold text-rose-800 hidden space-y-1">
                <div class="flex items-center gap-1.5 font-black text-rose-900">
                    <i data-lucide="alert-circle" class="h-4 w-4 shrink-0"></i>
                    <span id="vp-error-title">Please review the following:</span>
                </div>
                <div id="vp-error-message" class="text-[11px] font-medium leading-relaxed pl-5"></div>
            </div>

            {{-- 1. Vendor Section --}}
            <div class="space-y-1.5">
                <div class="flex items-center justify-between">
                    <label for="vp-vendor-select" class="block text-[11px] font-black uppercase tracking-wider text-slate-700">
                        Vendor <span class="text-rose-500">*</span>
                    </label>
                    <div class="flex items-center gap-2">
                        @if($shop->isVendorCreationAllowed())
                            <div id="vp-new-vendor-btn-container">
                                <button type="button" onclick="openShopOwnerCreateVendorModal()"
                                        class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-2 py-0.5 text-[10px] font-black text-emerald-700 border border-emerald-200 hover:bg-emerald-100 transition cursor-pointer">
                                    <i data-lucide="plus-circle" class="h-3 w-3"></i>
                                    <span>+ New Vendor</span>
                                </button>
                            </div>
                        @endif
                        <a href="{{ route('shop-owner.cashbook.vendors') }}" class="text-[10px] font-extrabold text-slate-500 hover:underline">
                            Settings &rarr; Vendors
                        </a>
                    </div>
                </div>

                <div id="vp-no-vendors-warning" class="{{ $linkedVendors->isEmpty() ? '' : 'hidden' }} rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs font-medium text-amber-800 flex items-start gap-2">
                    <i data-lucide="alert-triangle" class="h-4 w-4 shrink-0 text-amber-600 mt-0.5"></i>
                    <div>
                        <p class="font-bold text-amber-900" id="vp-no-vendors-title">No active vendors linked to this shop</p>
                        <p class="text-[11px] text-amber-700 mt-0.5" id="vp-no-vendors-desc">
                            @if($shop->isVendorCreationAllowed())
                                Click <button type="button" onclick="openShopOwnerCreateVendorModal()" class="font-black underline text-amber-900 cursor-pointer">+ New Vendor</button> above or link vendors in <a href="{{ route('shop-owner.cashbook.vendors') }}" class="font-black underline text-amber-900">Cashbook Settings &rarr; Vendors</a>.
                            @else
                                Link active vendors in <a href="{{ route('shop-owner.cashbook.vendors') }}" class="font-black underline text-amber-900">Cashbook Settings &rarr; Vendors</a> before recording purchases.
                            @endif
                        </p>
                    </div>
                </div>

                <div id="vp-vendor-dropdown-container" class="{{ $linkedVendors->isEmpty() ? 'hidden' : '' }} relative">
                    {{-- Hidden input for form submission & JS state --}}
                    <input type="hidden" id="vp-vendor-select" name="supplier_id" value="" data-credit="0" data-mobile="">

                    {{-- Custom Trigger Button --}}
                    <button type="button" id="vp-vendor-btn" onclick="toggleVpVendorDropdown(event)"
                            class="h-10 w-full flex items-center justify-between gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 text-left text-xs font-bold text-slate-900 focus:bg-white focus:border-emerald-500 focus:outline-none transition cursor-pointer">
                        <div class="min-w-0 flex-1">
                            <span id="vp-vendor-label" class="block truncate text-slate-400 font-medium">
                                -- Select Linked Active Vendor --
                            </span>
                            <span id="vp-vendor-sublabel" class="hidden text-[10px] font-semibold text-slate-500 font-mono truncate block"></span>
                        </div>
                        <svg class="h-4 w-4 shrink-0 text-slate-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    {{-- Floating Searchable Dropdown Panel --}}
                    <div id="vp-vendor-menu" onclick="event.stopPropagation()"
                         class="hidden absolute left-0 top-full mt-1 w-full z-40 rounded-xl border border-slate-200 bg-white shadow-2xl overflow-hidden">
                        <div class="p-2 border-b border-slate-100 bg-slate-50/80">
                            <div class="relative">
                                <input type="text" id="vp-vendor-search" placeholder="Search vendor name or phone..." autocomplete="off"
                                       oninput="filterVpVendorList()"
                                       onkeydown="handleVpVendorKeyNav(event)"
                                       class="h-8 w-full rounded-lg border border-slate-200 bg-white pl-7 pr-2.5 text-xs font-semibold text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none">
                                <svg class="absolute left-2 top-2 h-4 w-4 text-slate-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                        </div>
                        <div id="vp-vendor-options" class="max-h-52 overflow-y-auto divide-y divide-slate-100 py-1 text-xs">
                        </div>
                    </div>
                </div>
            </div>

            {{-- 2. Payment Section --}}
            <div class="space-y-1.5">
                <label class="block text-[11px] font-black uppercase tracking-wider text-slate-700">
                    Payment <span class="text-rose-500">*</span>
                </label>
                <div class="grid grid-cols-2 gap-2.5">
                    {{-- Cash Radio Option --}}
                    <label for="vp-payment-cash"
                           class="relative flex cursor-pointer items-start gap-2.5 rounded-xl border border-slate-200 bg-slate-50/70 p-2.5 transition hover:bg-white hover:border-emerald-300 has-checked:border-emerald-500 has-checked:bg-emerald-50/50 has-checked:ring-1 has-checked:ring-emerald-500">
                        <input type="radio" name="vp_payment_method" id="vp-payment-cash" value="Cash" checked
                               class="mt-0.5 h-4 w-4 text-emerald-600 focus:ring-emerald-500 border-slate-300">
                        <div class="min-w-0">
                            <span class="block text-xs font-black text-slate-900">Cash</span>
                            <span class="block text-[10px] font-medium text-slate-500 leading-tight">Shop cash flow</span>
                        </div>
                    </label>

                    {{-- Credit Radio Option --}}
                    <label for="vp-payment-credit" id="vp-payment-credit-label"
                           class="relative flex cursor-pointer items-start gap-2.5 rounded-xl border border-slate-200 bg-slate-50/70 p-2.5 transition hover:bg-white hover:border-emerald-300 has-checked:border-amber-500 has-checked:bg-amber-50/50 has-checked:ring-1 has-checked:ring-amber-500">
                        <input type="radio" name="vp_payment_method" id="vp-payment-credit" value="Credit"
                               class="mt-0.5 h-4 w-4 text-amber-600 focus:ring-amber-500 border-slate-300">
                        <div class="min-w-0">
                            <span class="block text-xs font-black text-slate-900">Credit</span>
                            <span id="vp-credit-subtext" class="block text-[10px] font-medium text-slate-500 leading-tight">Shop vendor liability</span>
                        </div>
                    </label>
                </div>
                <div id="vp-credit-notice" class="hidden text-[10px] font-bold text-amber-700 bg-amber-50 rounded-lg px-2.5 py-1 border border-amber-200/80">
                    <i data-lucide="info" class="h-3 w-3 inline mr-1 text-amber-600"></i>
                    <span>Credit payment is not approved for this vendor. Defaulted to Cash.</span>
                </div>
            </div>

            {{-- 3. Products Section --}}
            <div class="space-y-2 pt-1 border-t border-slate-100">
                <div class="flex items-center justify-between">
                    <label class="block text-[11px] font-black uppercase tracking-wider text-slate-700">
                        Products <span class="text-rose-500">*</span>
                    </label>
                    <button type="button" onclick="addVendorPurchaseRow()"
                            class="inline-flex items-center gap-1 rounded-full bg-emerald-50 border border-emerald-200/80 px-2.5 py-1 text-[11px] font-extrabold text-emerald-700 hover:bg-emerald-100 transition cursor-pointer active:scale-95">
                        <i data-lucide="plus" class="h-3.5 w-3.5"></i>
                        <span>Add Item</span>
                    </button>
                </div>

                {{-- Column Headers (Hidden on tiny screens, clear on md+) --}}
                <div class="hidden sm:grid sm:grid-cols-12 gap-2 text-[10px] font-black uppercase tracking-wider text-slate-400 px-1">
                    <div class="col-span-5">Product</div>
                    <div class="col-span-3">Qty</div>
                    <div class="col-span-3">Total Price (₹)</div>
                    <div class="col-span-1 text-center"></div>
                </div>

                {{-- Rows Container --}}
                <div id="vp-products-list" class="space-y-2">
                    {{-- Dynamically rendered via JS --}}
                </div>

                {{-- Total Purchase Value Row --}}
                <div class="flex items-center justify-between rounded-xl bg-slate-50 p-2.5 sm:p-3 border border-slate-200/80">
                    <span class="text-xs font-black uppercase tracking-wider text-slate-600">Total Purchase Value:</span>
                    <span id="vp-grand-total" class="font-mono text-sm sm:text-base font-black text-slate-950">₹0.00</span>
                </div>
            </div>

            {{-- 4. Bill / Reference & Note Section --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 pt-1 border-t border-slate-100">
                <div class="space-y-1">
                    <label for="vp-bill-number" class="block text-[11px] font-black uppercase tracking-wider text-slate-700">
                        Bill / Reference
                    </label>
                    <input type="text" id="vp-bill-number" placeholder="e.g. INV-10482 (Optional)"
                           class="h-9 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:border-emerald-500 focus:outline-none transition">
                </div>
                <div class="space-y-1">
                    <label for="vp-notes" class="block text-[11px] font-black uppercase tracking-wider text-slate-700">
                        Note
                    </label>
                    <input type="text" id="vp-notes" placeholder="Remarks / description (Optional)"
                           class="h-9 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:border-emerald-500 focus:outline-none transition">
                </div>
            </div>
        </form>

        {{-- Footer Actions --}}
        <div class="flex items-center justify-end gap-2.5 border-t border-slate-100 pt-3 shrink-0">
            <button type="button" onclick="closeVendorPurchaseModal()"
                    class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 transition cursor-pointer">
                Cancel
            </button>
            <button type="button" id="vp-submit-btn" onclick="submitVendorPurchase()"
                    class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-5 py-2 text-xs font-black text-white shadow-xs hover:bg-emerald-700 active:scale-[0.98] transition cursor-pointer">
                <span id="vp-submit-spinner" class="hidden">
                    <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                </span>
                <span id="vp-submit-text">Save Purchase</span>
            </button>
        </div>
    </div>
</div>

{{-- SHOP OWNER NEW VENDOR MODAL --}}
@if($shop->isVendorCreationAllowed())
<div id="vp-new-vendor-modal" onclick="handleModalBackdropClick(event, 'vp-new-vendor-modal')"
     class="fixed inset-0 z-[70] flex items-end sm:items-center justify-center bg-slate-900/60 backdrop-blur-xs hidden transition-all duration-200 pb-[calc(env(safe-area-inset-bottom,0px)+5.25rem)] sm:pb-0 px-0 sm:px-4">
    <div onclick="event.stopPropagation()"
         class="w-full max-w-md rounded-t-2xl sm:rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-2xl flex flex-col max-h-[82vh] sm:max-h-[90vh]">

        {{-- Header --}}
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 shrink-0">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-800 border border-emerald-200 flex items-center justify-center shrink-0">
                    <i data-lucide="user-plus" class="h-4 w-4"></i>
                </div>
                <div>
                    <h3 class="text-xs sm:text-sm font-black uppercase tracking-wider text-slate-900">
                        NEW VENDOR
                    </h3>
                    <p class="text-[11px] font-bold text-slate-400">
                        Add &amp; link vendor for {{ $shop->name }}
                    </p>
                </div>
            </div>
            <button type="button" aria-label="Close" onclick="closeShopOwnerCreateVendorModal()"
                    class="h-8 w-8 min-h-[32px] min-w-[32px] inline-flex items-center justify-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 hover:text-slate-900 active:scale-95 transition cursor-pointer shrink-0">
                <i data-lucide="x" class="h-4 w-4"></i>
            </button>
        </div>

        {{-- Form --}}
        <form id="vp-new-vendor-form" onsubmit="event.preventDefault(); submitShopOwnerCreateVendor();" class="flex-1 overflow-y-auto pt-3.5 pb-2 space-y-3">
            {{-- Error container --}}
            <div id="vp-new-vendor-error" class="rounded-xl border border-rose-200 bg-rose-50/90 p-3 text-xs font-bold text-rose-800 hidden space-y-1">
                <div class="flex items-center gap-1.5 font-black text-rose-900">
                    <i data-lucide="alert-circle" class="h-4 w-4 shrink-0"></i>
                    <span>Please correct the errors:</span>
                </div>
                <div id="vp-new-vendor-error-msg" class="text-[11px] font-medium leading-relaxed pl-5"></div>
            </div>

            {{-- Notice: Credit Approval --}}
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-[11px] font-medium text-slate-600 flex items-start gap-2">
                <i data-lucide="info" class="h-4 w-4 text-slate-500 shrink-0 mt-0.5"></i>
                <p>Newly created vendors can be used for <strong>Cash Purchases</strong> immediately. Vendor credit must be enabled by Admin.</p>
            </div>

            <div>
                <label for="vp-nv-name" class="block text-[11px] font-black uppercase tracking-wider text-slate-700">
                    Vendor Name <span class="text-rose-500">*</span>
                </label>
                <input type="text" id="vp-nv-name" required placeholder="e.g. Ramesh Fruits"
                       class="mt-1 h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:bg-white focus:border-emerald-500 focus:outline-none transition">
            </div>

            <div>
                <label for="vp-nv-mobile" class="block text-[11px] font-black uppercase tracking-wider text-slate-700">
                    Mobile Number
                </label>
                <input type="text" id="vp-nv-mobile" placeholder="e.g. 9845012345"
                       class="mt-1 h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:bg-white focus:border-emerald-500 focus:outline-none transition">
            </div>

            <div>
                <label for="vp-nv-contact" class="block text-[11px] font-black uppercase tracking-wider text-slate-700">
                    Contact / GST / Tax Details
                </label>
                <input type="text" id="vp-nv-contact" placeholder="e.g. Contact Person, GSTIN..."
                       class="mt-1 h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:bg-white focus:border-emerald-500 focus:outline-none transition">
            </div>

            <div>
                <label for="vp-nv-notes" class="block text-[11px] font-black uppercase tracking-wider text-slate-700">
                    Address / Note
                </label>
                <textarea id="vp-nv-notes" rows="2" placeholder="e.g. Shop #12 APMC Yard..."
                          class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-xs font-medium text-slate-900 focus:bg-white focus:border-emerald-500 focus:outline-none transition"></textarea>
            </div>

            <div class="pt-2 flex items-center justify-end gap-2 border-t border-slate-100">
                <button type="button" onclick="closeShopOwnerCreateVendorModal()"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                    Cancel
                </button>
                <button type="submit" id="vp-nv-submit-btn"
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-black text-white hover:bg-emerald-700 active:scale-98 transition shadow-xs cursor-pointer">
                    <span id="vp-nv-spinner" class="hidden">
                        <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                    </span>
                    <span id="vp-nv-submit-text">Create Vendor</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endif
