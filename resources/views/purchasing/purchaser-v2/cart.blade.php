<x-layouts.purchaser-v2 title="Purchaser V2 &bull; Draft Cart Hub">
    <div class="space-y-4 sm:space-y-6">

        <!-- Top Header & Context Bar -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('purchaser-v2.buy', ['date' => $date, 'grade' => $grade]) }}"
                   class="flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50 hover:text-slate-900 active:scale-95">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl font-black tracking-tight text-slate-900 sm:text-2xl">Draft Carts</h1>
                        <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-black text-emerald-700 border border-emerald-200">
                            Grade {{ $grade }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 font-medium">Manage draft purchase carts, assign suppliers, edit items, or proceed to bill.</p>
                </div>
            </div>

            <!-- Action & Date Header -->
            <div class="flex items-center gap-2 self-start sm:self-auto">
                <span class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 shadow-sm">
                    <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    {{ \Illuminate\Support\Carbon::parse($date)->format('D, d M Y') }}
                </span>
                <a href="{{ route('purchaser-v2.buy', ['date' => $date, 'grade' => $grade]) }}"
                   class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-1.5 text-xs font-bold text-white shadow-sm shadow-emerald-600/20 transition hover:bg-emerald-700 active:scale-95">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Add Products</span>
                </a>
            </div>
        </div>

        <!-- Flash Message Banner -->
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4 text-xs font-bold text-emerald-900 flex items-center gap-3">
                <svg class="h-5 w-5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        <!-- Draft Carts Feed -->
        <div id="cartsFeed" class="space-y-4">
            @forelse ($draftCarts as $cart)
                <div class="cart-card rounded-3xl border border-slate-200 bg-white p-5 shadow-sm space-y-4 transition hover:border-slate-300"
                     data-cart-id="{{ $cart['id'] }}"
                     data-cart-number="{{ $cart['cart_number'] }}">
                    
                    <!-- Cart Header -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
                        <div class="flex items-center gap-3">
                            <span class="rounded-xl bg-slate-100 px-3 py-1 text-xs font-black font-mono text-slate-800">
                                {{ $cart['cart_number'] }}
                            </span>
                            
                            <!-- Supplier Badge / Button -->
                            <div class="flex items-center gap-1.5">
                                @if ($cart['supplier_id'])
                                    <span class="inline-flex items-center gap-1 rounded-xl bg-sky-50 px-2.5 py-1 text-xs font-bold text-sky-800 border border-sky-200">
                                        <svg class="h-3.5 w-3.5 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                        </svg>
                                        <span class="supplier-name-display">{{ $cart['supplier_name'] }}</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-xl bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-800 border border-amber-200">
                                        <svg class="h-3.5 w-3.5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                        <span class="supplier-name-display">No Supplier Assigned</span>
                                    </span>
                                @endif

                                <button type="button" class="btn-change-supplier text-[11px] font-bold text-slate-500 hover:text-emerald-600 p-1" title="Change Supplier">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Stats & Updated -->
                        <div class="flex items-center gap-3 text-xs font-mono">
                            <span class="text-slate-500">{{ $cart['item_count'] }} {{ $cart['item_count'] === 1 ? 'item' : 'items' }}</span>
                            <span class="text-slate-300">&bull;</span>
                            <span class="font-black text-slate-900">₹{{ number_format($cart['subtotal'], 2) }}</span>
                            <span class="text-slate-300">&bull;</span>
                            <span class="text-slate-400 text-[11px] font-sans">{{ $cart['updated_at_human'] }}</span>
                        </div>
                    </div>

                    <!-- Cart Actions Bar -->
                    <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                        <div class="flex items-center gap-2">
                            <!-- Toggle Items Button -->
                            <button type="button" class="btn-toggle-items rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 active:scale-95 flex items-center gap-1.5">
                                <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                                <span>View / Edit Items</span>
                            </button>

                            <!-- Merge Button (if mergeable) -->
                            @if ($cart['mergeable_cart_count'] > 0)
                                <button type="button" class="btn-merge-drafts rounded-xl border border-purple-200 bg-purple-50 px-3 py-2 text-xs font-bold text-purple-800 shadow-sm transition hover:bg-purple-100 active:scale-95 flex items-center gap-1">
                                    <svg class="h-4 w-4 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                    </svg>
                                    <span>Merge Drafts ({{ $cart['mergeable_cart_count'] }})</span>
                                </button>
                            @endif

                            <!-- Delete Cart Button -->
                            <button type="button" class="btn-delete-cart text-slate-400 hover:text-rose-600 transition p-2" title="Delete Draft Cart">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>

                        <!-- Continue to Bill (Direct Handoff to Legacy Bill Page) -->
                        <a href="{{ route('purchaser.bill', ['cart' => $cart['cart_number']]) }}"
                           class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-slate-800 active:scale-95 flex items-center gap-1.5">
                            <span>Continue to Bill</span>
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    </div>

                    <!-- Expandable On-Demand Cart Items Section (Lazy Loaded) -->
                    <div class="cart-items-section hidden pt-4 border-t border-slate-100 space-y-3">
                        <div class="items-loading text-center py-6 text-slate-400 text-xs flex items-center justify-center gap-2">
                            <svg class="animate-spin h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                            </svg>
                            <span>Loading cart items...</span>
                        </div>
                        <div class="items-container space-y-2 hidden"></div>
                        <div class="items-footer hidden flex items-center justify-between pt-3 border-t border-slate-100">
                            <span class="text-xs font-bold text-slate-500">Cart Total: <strong class="text-slate-900 font-mono cart-total-live">₹0.00</strong></span>
                            <button type="button" class="btn-save-items rounded-xl bg-emerald-600 px-4 py-1.5 text-xs font-bold text-white shadow-sm hover:bg-emerald-700 active:scale-95">
                                Save Item Changes
                            </button>
                        </div>
                    </div>

                </div>
            @empty
                <div class="rounded-3xl border border-dashed border-slate-200 bg-white p-12 text-center text-slate-400 space-y-3">
                    <svg class="mx-auto h-10 w-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                    <p class="text-xs font-bold text-slate-600">No active draft carts found for this date.</p>
                    <a href="{{ route('purchaser-v2.buy', ['date' => $date, 'grade' => $grade]) }}" class="inline-block rounded-xl bg-slate-900 px-4 py-2 text-xs font-bold text-white transition hover:bg-slate-800">
                        Go to Buy Workspace &rarr;
                    </a>
                </div>
            @endforelse
        </div>

    </div>

    <!-- Supplier Assignment Modal (Searchable Server-Side + Quick Add Supplier) -->
    <div id="supplierModal" class="fixed inset-0 z-50 hidden flex items-end sm:items-center justify-center p-0 sm:p-4 bg-slate-900/60 backdrop-blur-xs overscroll-none transition-all">
        <div class="w-full sm:max-w-lg max-h-[90vh] sm:max-h-[82vh] flex flex-col rounded-t-[2rem] sm:rounded-3xl bg-white shadow-2xl border border-slate-100 overflow-hidden touch-pan-y">
            
            <!-- Sticky Fixed Header (Always visible at top on mobile) -->
            <div class="p-4 sm:p-5 pb-3 border-b border-slate-100 shrink-0 bg-white space-y-3">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-black text-slate-900 flex items-center gap-2">
                            <span>Supplier Assignment</span>
                            <span id="modalActiveCartLabel" class="rounded-lg bg-slate-100 px-2 py-0.5 text-[10px] font-mono font-bold text-slate-600"></span>
                        </h3>
                        <p class="text-[11px] text-slate-500 font-medium">Search existing vendor or create a new one</p>
                    </div>
                    <button type="button" id="btnCloseSupplierModal" class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Tabs: Search Existing vs Add New -->
                <div class="flex items-center gap-1.5 p-1 rounded-2xl bg-slate-100 text-xs font-bold">
                    <button type="button" id="tabSearchSupplier" class="flex-1 py-1.5 px-3 rounded-xl bg-white text-slate-900 shadow-2xs transition flex items-center justify-center gap-1.5">
                        <svg class="h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <span>Search Vendor</span>
                    </button>
                    <button type="button" id="tabAddSupplier" class="flex-1 py-1.5 px-3 rounded-xl text-slate-500 hover:text-slate-900 transition flex items-center justify-center gap-1.5">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>+ Add New</span>
                    </button>
                </div>

                <!-- Search Input Field (Visible on Search Tab) -->
                <div id="searchBarContainer" class="relative">
                    <input type="text" id="supplierSearchInput" placeholder="Search supplier by name or phone..."
                           class="h-10 w-full rounded-2xl border border-slate-200 bg-slate-50 pl-10 pr-9 text-xs font-bold text-slate-900 placeholder:text-slate-400 focus:border-emerald-600 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-600/20">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <button type="button" id="btnClearSupplierSearch" class="hidden absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Scrollable Content Section -->
            <div class="flex-1 overflow-y-auto p-4 sm:p-5 pt-2">
                <!-- Search Results Feed -->
                <div id="panelSupplierSearch" class="space-y-2">
                    <div id="supplierSearchResults" class="divide-y divide-slate-100 pr-1 space-y-1">
                        <div class="p-6 text-center text-xs text-slate-400">Loading suppliers...</div>
                    </div>
                </div>

                <!-- Add New Supplier Form Panel -->
                <form id="panelAddSupplier" class="hidden space-y-3">
                    <div class="p-3 rounded-2xl bg-emerald-50/70 border border-emerald-200/80 text-[11px] text-emerald-900 font-medium">
                        Quickly register a new vendor. It will be added to the vendor master and assigned to this cart immediately.
                    </div>

                    <div>
                        <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Supplier / Vendor Name *</label>
                        <input type="text" id="newSupplierName" required placeholder="e.g. Ramesh Fruits"
                               class="mt-1 h-10 w-full rounded-2xl border border-slate-200 bg-slate-50 px-3.5 text-xs font-bold text-slate-900 focus:bg-white focus:border-emerald-600 focus:outline-none">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Mobile Number (Optional)</label>
                            <input type="tel" id="newSupplierMobile" placeholder="e.g. 9876543210"
                                   class="mt-1 h-10 w-full rounded-2xl border border-slate-200 bg-slate-50 px-3.5 text-xs font-bold text-slate-900 focus:bg-white focus:border-emerald-600 focus:outline-none">
                        </div>
                        <div>
                            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">Location / Market (Optional)</label>
                            <input type="text" id="newSupplierLocation" placeholder="e.g. City Market"
                                   class="mt-1 h-10 w-full rounded-2xl border border-slate-200 bg-slate-50 px-3.5 text-xs font-bold text-slate-900 focus:bg-white focus:border-emerald-600 focus:outline-none">
                        </div>
                    </div>

                    <div id="addSupplierError" class="hidden text-xs font-bold text-rose-600 p-2"></div>

                    <div class="pt-2 flex items-center gap-2">
                        <button type="button" id="btnCancelAddSupplier" class="h-10 px-4 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 hover:bg-slate-50 transition">
                            Back to Search
                        </button>
                        <button type="submit" id="btnSubmitAddSupplier" class="h-10 flex-1 rounded-xl bg-emerald-600 px-4 text-xs font-black text-white shadow-sm hover:bg-emerald-500 active:scale-95 transition flex items-center justify-center gap-1.5">
                            <span>Save & Assign to Cart</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Client-Side Vanilla JS Logic -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const supplierModal = document.getElementById('supplierModal');
            const btnCloseSupplierModal = document.getElementById('btnCloseSupplierModal');
            const supplierSearchInput = document.getElementById('supplierSearchInput');
            const btnClearSupplierSearch = document.getElementById('btnClearSupplierSearch');
            const supplierSearchResults = document.getElementById('supplierSearchResults');
            const modalActiveCartLabel = document.getElementById('modalActiveCartLabel');

            const tabSearchSupplier = document.getElementById('tabSearchSupplier');
            const tabAddSupplier = document.getElementById('tabAddSupplier');
            const searchBarContainer = document.getElementById('searchBarContainer');
            const panelSupplierSearch = document.getElementById('panelSupplierSearch');
            const panelAddSupplier = document.getElementById('panelAddSupplier');
            const btnCancelAddSupplier = document.getElementById('btnCancelAddSupplier');
            const btnSubmitAddSupplier = document.getElementById('btnSubmitAddSupplier');
            const addSupplierError = document.getElementById('addSupplierError');

            let activeCartIdForSupplier = null;
            let supplierSearchTimer = null;
            let supplierAbortController = null;

            // Switch Tabs inside Supplier Modal
            function switchSupplierTab(mode) {
                if (mode === 'search') {
                    tabSearchSupplier.className = 'flex-1 py-1.5 px-3 rounded-xl bg-white text-slate-900 shadow-2xs transition flex items-center justify-center gap-1.5';
                    tabAddSupplier.className = 'flex-1 py-1.5 px-3 rounded-xl text-slate-500 hover:text-slate-900 transition flex items-center justify-center gap-1.5';
                    searchBarContainer.classList.remove('hidden');
                    panelSupplierSearch.classList.remove('hidden');
                    panelAddSupplier.classList.add('hidden');
                    supplierSearchInput.focus();
                } else {
                    tabAddSupplier.className = 'flex-1 py-1.5 px-3 rounded-xl bg-white text-slate-900 shadow-2xs transition flex items-center justify-center gap-1.5';
                    tabSearchSupplier.className = 'flex-1 py-1.5 px-3 rounded-xl text-slate-500 hover:text-slate-900 transition flex items-center justify-center gap-1.5';
                    searchBarContainer.classList.add('hidden');
                    panelSupplierSearch.classList.add('hidden');
                    panelAddSupplier.classList.remove('hidden');
                    document.getElementById('newSupplierName').focus();
                }
            }

            tabSearchSupplier.addEventListener('click', () => switchSupplierTab('search'));
            tabAddSupplier.addEventListener('click', () => switchSupplierTab('add'));
            btnCancelAddSupplier.addEventListener('click', () => switchSupplierTab('search'));

            // Close Supplier Modal
            function closeSupplierModal() {
                supplierModal.classList.add('hidden');
                activeCartIdForSupplier = null;
            }

            btnCloseSupplierModal.addEventListener('click', closeSupplierModal);
            supplierModal.addEventListener('click', (e) => {
                if (e.target === supplierModal) closeSupplierModal();
            });

            // Clear search
            btnClearSupplierSearch.addEventListener('click', () => {
                supplierSearchInput.value = '';
                btnClearSupplierSearch.classList.add('hidden');
                performSupplierSearch('');
                supplierSearchInput.focus();
            });

            // Supplier Search Debounce
            supplierSearchInput.addEventListener('input', (e) => {
                const query = e.target.value.trim();
                btnClearSupplierSearch.classList.toggle('hidden', query === '');
                clearTimeout(supplierSearchTimer);

                supplierSearchTimer = setTimeout(() => {
                    performSupplierSearch(query);
                }, 300);
            });

            async function performSupplierSearch(query) {
                if (supplierAbortController) {
                    supplierAbortController.abort();
                }
                supplierAbortController = new AbortController();

                try {
                    supplierSearchResults.innerHTML = '<div class="p-6 text-center text-xs text-slate-400">Searching vendors...</div>';
                    const res = await fetch(`{{ route('purchaser-v2.suppliers.search') }}?q=${encodeURIComponent(query)}`, {
                        headers: { 'Accept': 'application/json' },
                        signal: supplierAbortController.signal,
                    });
                    const json = await res.json();

                    if (json.status === 'success' && json.data.length > 0) {
                        supplierSearchResults.innerHTML = json.data.map(s => `
                            <div class="flex items-center justify-between p-3 rounded-2xl transition hover:bg-slate-50 cursor-pointer group btn-select-supplier"
                                 data-id="${s.id}" data-name="${s.name}">
                                <div class="min-w-0 pr-2">
                                    <p class="text-xs font-bold text-slate-900 truncate group-hover:text-emerald-600 transition">${s.name}</p>
                                    <p class="text-[10px] text-slate-400 font-mono mt-0.5">${s.location || 'Local'} &bull; ${s.mobile_number || 'No phone'}</p>
                                </div>
                                <button type="button" class="shrink-0 rounded-xl bg-sky-50 px-3 py-1.5 text-xs font-bold text-sky-700 border border-sky-200 transition group-hover:bg-sky-600 group-hover:text-white">
                                    Select
                                </button>
                            </div>
                        `).join('');

                        document.querySelectorAll('.btn-select-supplier').forEach(el => {
                            el.addEventListener('click', async () => {
                                const supplierId = parseInt(el.dataset.id, 10);
                                const supplierName = el.dataset.name;
                                await assignSupplierToCart(activeCartIdForSupplier, supplierId, supplierName);
                            });
                        });
                    } else {
                        supplierSearchResults.innerHTML = `
                            <div class="p-8 text-center space-y-3">
                                <p class="text-xs text-slate-400">No matching suppliers found for "${query}".</p>
                                <button type="button" id="btnQuickCreateFromSearch" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-50 border border-emerald-200 text-xs font-bold text-emerald-800 hover:bg-emerald-100 transition">
                                    <span>+ Add "${query || 'New Supplier'}"</span>
                                </button>
                            </div>
                        `;
                        const quickBtn = document.getElementById('btnQuickCreateFromSearch');
                        if (quickBtn) {
                            quickBtn.addEventListener('click', () => {
                                document.getElementById('newSupplierName').value = query;
                                switchSupplierTab('add');
                            });
                        }
                    }
                } catch (err) {
                    if (err.name !== 'AbortError') {
                        supplierSearchResults.innerHTML = '<div class="p-6 text-center text-xs text-rose-500">Error searching suppliers.</div>';
                    }
                }
            }

            // Quick Add New Supplier Submit
            panelAddSupplier.addEventListener('submit', async (e) => {
                e.preventDefault();
                addSupplierError.classList.add('hidden');
                addSupplierError.textContent = '';

                const name = document.getElementById('newSupplierName').value.trim();
                const mobile = document.getElementById('newSupplierMobile').value.trim();
                const location = document.getElementById('newSupplierLocation').value.trim();

                if (!name) return;

                btnSubmitAddSupplier.disabled = true;
                btnSubmitAddSupplier.classList.add('opacity-50');

                try {
                    const res = await fetch(`{{ route('purchaser-v2.suppliers.store') }}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            name: name,
                            mobile_number: mobile || null,
                            location: location || null,
                            cart_id: activeCartIdForSupplier
                        })
                    });

                    const json = await res.json();

                    if (json.status === 'success' && json.supplier) {
                        const cartCard = document.querySelector(`.cart-card[data-cart-id="${activeCartIdForSupplier}"], .cart-card[data-cart-number="${activeCartIdForSupplier}"]`);
                        if (cartCard) {
                            const nameDisplay = cartCard.querySelector('.supplier-name-display');
                            if (nameDisplay) {
                                nameDisplay.textContent = json.supplier.name;
                            }
                        }
                        panelAddSupplier.reset();
                        closeSupplierModal();
                    } else {
                        addSupplierError.textContent = json.message || 'Failed to create supplier.';
                        addSupplierError.classList.remove('hidden');
                    }
                } catch (err) {
                    addSupplierError.textContent = 'Network or server error creating supplier.';
                    addSupplierError.classList.remove('hidden');
                } finally {
                    btnSubmitAddSupplier.disabled = false;
                    btnSubmitAddSupplier.classList.remove('opacity-50');
                }
            });

            async function assignSupplierToCart(cartId, supplierId, supplierName) {
                try {
                    const res = await fetch(`{{ url('/purchaser-v2/cart') }}/${cartId}/supplier`, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ supplier_id: supplierId })
                    });
                    const json = await res.json();

                    if (json.status === 'success') {
                        const cartCard = document.querySelector(`.cart-card[data-cart-id="${cartId}"], .cart-card[data-cart-number="${cartId}"]`);
                        if (cartCard) {
                            const nameDisplay = cartCard.querySelector('.supplier-name-display');
                            if (nameDisplay) {
                                nameDisplay.textContent = supplierName;
                            }
                        }
                        closeSupplierModal();
                    }
                } catch (err) {
                    console.error('Error assigning supplier:', err);
                }
            }

            // Bind Cart Card Actions
            document.querySelectorAll('.cart-card').forEach(card => {
                const cartId = card.dataset.cartId;
                const toggleBtn = card.querySelector('.btn-toggle-items');
                const itemsSection = card.querySelector('.cart-items-section');
                const loadingIndicator = card.querySelector('.items-loading');
                const itemsContainer = card.querySelector('.items-container');
                const itemsFooter = card.querySelector('.items-footer');
                const totalDisplay = card.querySelector('.cart-total-live');
                const saveBtn = card.querySelector('.btn-save-items');
                const changeSupplierBtn = card.querySelector('.btn-change-supplier');
                const deleteCartBtn = card.querySelector('.btn-delete-cart');
                const mergeBtn = card.querySelector('.btn-merge-drafts');

                let itemsLoaded = false;

                // Change Supplier
                if (changeSupplierBtn) {
                    changeSupplierBtn.addEventListener('click', () => {
                        activeCartIdForSupplier = cartId;
                        supplierModal.classList.remove('hidden');
                        supplierSearchInput.value = '';
                        performSupplierSearch('');
                        supplierSearchInput.focus();
                    });
                }

                // Delete Cart
                if (deleteCartBtn) {
                    deleteCartBtn.addEventListener('click', async () => {
                        if (!confirm('Are you sure you want to delete this draft cart?')) return;

                        try {
                            const res = await fetch(`{{ url('/purchaser-v2/cart') }}/${cartId}`, {
                                method: 'DELETE',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                }
                            });
                            const json = await res.json();
                            if (json.status === 'success') {
                                card.remove();
                            }
                        } catch (err) {
                            console.error('Error deleting cart:', err);
                        }
                    });
                }

                // Merge Drafts
                if (mergeBtn) {
                    mergeBtn.addEventListener('click', async () => {
                        if (!confirm('Merge compatible draft carts into this cart?')) return;

                        try {
                            const res = await fetch(`{{ url('/purchaser-v2/cart') }}/${cartId}/merge-drafts`, {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                }
                            });
                            const json = await res.json();
                            if (json.status === 'success') {
                                window.location.reload();
                            }
                        } catch (err) {
                            console.error('Error merging drafts:', err);
                        }
                    });
                }

                // Toggle / Lazy Load Items
                toggleBtn.addEventListener('click', async () => {
                    const isHidden = itemsSection.classList.contains('hidden');

                    if (isHidden) {
                        itemsSection.classList.remove('hidden');
                        if (!itemsLoaded) {
                            await loadCartItems();
                        }
                    } else {
                        itemsSection.classList.add('hidden');
                    }
                });

                async function loadCartItems() {
                    try {
                        const res = await fetch(`{{ url('/purchaser-v2/cart') }}/${cartId}/items`, {
                            headers: { 'Accept': 'application/json' }
                        });
                        const json = await res.json();

                        loadingIndicator.classList.add('hidden');

                        if (json.status === 'success' && json.items.length > 0) {
                            itemsContainer.innerHTML = json.items.map(item => `
                                <div class="item-row flex flex-col sm:flex-row sm:items-center justify-between gap-2 p-2.5 rounded-2xl bg-slate-50 border border-slate-100"
                                     data-item-id="${item.id}">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <p class="text-xs font-bold text-slate-900 truncate">${item.name}</p>
                                            <span class="rounded-md ${item.is_extra_purchase ? 'bg-purple-50 text-purple-700 border-purple-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'} px-1 py-0.5 text-[9px] font-bold border">
                                                ${item.is_extra_purchase ? 'Extra' : 'Regular'}
                                            </span>
                                        </div>
                                        <p class="text-[10px] text-slate-400 font-mono">${item.category_name} &bull; SKU: ${item.sku}</p>
                                    </div>
                                    <div class="flex items-center gap-2 self-end sm:self-auto">
                                        <div class="flex items-center gap-1 font-mono">
                                            <input type="number" step="any" min="0.01" value="${item.quantity}"
                                                   class="item-qty h-8 w-16 rounded-lg border border-slate-200 bg-white px-1.5 text-xs font-bold text-slate-900 text-right">
                                            <span class="text-[10px] text-slate-400">${item.unit}</span>
                                        </div>
                                        <span class="text-slate-300">&times;</span>
                                        <div class="flex items-center gap-1 font-mono">
                                            <span class="text-[10px] text-slate-400">₹</span>
                                            <input type="number" step="0.01" min="0" value="${item.unit_price.toFixed(2)}"
                                                   class="item-price h-8 w-16 rounded-lg border border-slate-200 bg-white px-1.5 text-xs font-bold text-slate-900 text-right">
                                        </div>
                                        <span class="text-slate-300">=</span>
                                        <span class="item-total-display text-xs font-black text-slate-900 font-mono w-20 text-right">
                                            ₹${item.line_total.toFixed(2)}
                                        </span>
                                        <button type="button" class="btn-delete-item text-slate-400 hover:text-rose-600 p-1" title="Remove item">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            `).join('');

                            itemsContainer.classList.remove('hidden');
                            itemsFooter.classList.remove('hidden');
                            itemsLoaded = true;

                            attachItemRowListeners();
                            recalcCartTotal();
                        } else {
                            itemsContainer.innerHTML = '<div class="p-4 text-center text-xs text-slate-400">Cart is empty.</div>';
                            itemsContainer.classList.remove('hidden');
                        }
                    } catch (err) {
                        loadingIndicator.innerHTML = '<span class="text-rose-500 text-xs">Error loading cart items.</span>';
                    }
                }

                function attachItemRowListeners() {
                    itemsContainer.querySelectorAll('.item-row').forEach(row => {
                        const qtyInput = row.querySelector('.item-qty');
                        const priceInput = row.querySelector('.item-price');
                        const deleteBtn = row.querySelector('.btn-delete-item');
                        const itemId = row.dataset.itemId;

                        qtyInput.addEventListener('input', recalcCartTotal);
                        priceInput.addEventListener('input', recalcCartTotal);

                        deleteBtn.addEventListener('click', async () => {
                            if (!confirm('Remove this item from the cart?')) return;

                            try {
                                const res = await fetch(`{{ url('/purchaser-v2/cart') }}/${cartId}/items/${itemId}`, {
                                    method: 'DELETE',
                                    headers: {
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                    }
                                });
                                const json = await res.json();
                                if (json.status === 'success') {
                                    if (json.cart_deleted) {
                                        card.remove();
                                    } else {
                                        row.remove();
                                        recalcCartTotal();
                                    }
                                }
                            } catch (err) {
                                console.error('Error deleting item:', err);
                            }
                        });
                    });
                }

                function recalcCartTotal() {
                    let total = 0;
                    itemsContainer.querySelectorAll('.item-row').forEach(row => {
                        const qty = parseFloat(row.querySelector('.item-qty')?.value || '0');
                        const price = parseFloat(row.querySelector('.item-price')?.value || '0');
                        const lineDisplay = row.querySelector('.item-total-display');
                        const line = qty * price;
                        if (lineDisplay) {
                            lineDisplay.textContent = '₹' + line.toFixed(2);
                        }
                        total += line;
                    });
                    totalDisplay.textContent = '₹' + total.toFixed(2);
                }

                // Save Item Changes
                saveBtn.addEventListener('click', async () => {
                    const itemsPayload = [];
                    itemsContainer.querySelectorAll('.item-row').forEach(row => {
                        itemsPayload.push({
                            id: parseInt(row.dataset.itemId, 10),
                            quantity: parseFloat(row.querySelector('.item-qty')?.value || '0'),
                            unit_price: parseFloat(row.querySelector('.item-price')?.value || '0'),
                        });
                    });

                    try {
                        saveBtn.disabled = true;
                        saveBtn.textContent = 'Saving...';
                        const res = await fetch(`{{ url('/purchaser-v2/cart') }}/${cartId}/items`, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({ items: itemsPayload })
                        });
                        const json = await res.json();
                        saveBtn.disabled = false;
                        saveBtn.textContent = 'Save Item Changes';

                        if (json.status === 'success') {
                            alert('Cart items updated successfully.');
                        }
                    } catch (err) {
                        saveBtn.disabled = false;
                        saveBtn.textContent = 'Save Item Changes';
                        alert('Error updating cart items.');
                    }
                });
            });
        });
    </script>
</x-layouts.purchaser-v2>
