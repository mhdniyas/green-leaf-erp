{{-- DELIVERIES-STYLE PRODUCT SELECTION MODAL --}}
<div id="owner-product-modal" onclick="handleModalBackdropClick(event, 'owner-product-modal')" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-xs p-3 sm:p-4 hidden transition-all duration-200">
    <div onclick="event.stopPropagation()" class="w-full max-w-lg rounded-2xl border border-slate-100 bg-white p-3.5 sm:p-4 shadow-2xl space-y-2.5">
        <div class="flex items-center justify-between border-b border-slate-100 pb-2.5 gap-2">
            <div class="flex items-center gap-2 min-w-0 flex-1">
                <div class="w-8 h-8 rounded-full bg-white border border-slate-200/80 shadow-2xs flex items-center justify-center shrink-0">
                    <img src="{{ asset('images/greenleaf-logo.png') }}" alt="GL" class="h-4 w-4 object-contain">
                </div>
                <h3 class="text-xs sm:text-sm font-black uppercase tracking-wide text-slate-900 min-w-0 flex-1 truncate" id="owner-product-modal-title">
                    <span class="truncate">Select Product</span>
                </h3>
            </div>
            <button type="button" aria-label="Close" onclick="closeOwnerProductModal()"
                    class="h-8 w-8 min-h-[32px] min-w-[32px] inline-flex items-center justify-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 hover:text-slate-900 active:scale-95 transition cursor-pointer shrink-0">
                <i data-lucide="x" class="h-4 w-4"></i>
            </button>
        </div>

        <div class="space-y-2">
            <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <i data-lucide="search" class="h-3.5 w-3.5"></i>
                </span>
                <input type="text" id="owner-product-search-input" oninput="onOwnerProductSearchInput()" placeholder="Search products by name or SKU..."
                       class="h-8 w-full rounded-xl border border-slate-200 bg-slate-50/80 pl-9 pr-3 text-xs font-bold text-slate-900 placeholder:text-slate-400 focus:bg-white focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/20 focus:outline-none transition shadow-2xs">
            </div>

            <div id="owner-product-list" class="max-h-60 overflow-y-auto space-y-1.5 pr-0.5">
                <div class="p-4 text-center text-xs font-bold text-slate-400 bg-slate-50 rounded-xl border border-slate-100">Search products...</div>
            </div>
        </div>
    </div>
</div>
