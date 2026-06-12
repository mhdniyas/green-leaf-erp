<x-layouts.app title="Purchaser Dashboard">
    <div class="mx-auto px-4 py-6 max-w-lg lg:max-w-2xl pb-24">
        {{-- Header Section --}}
        <div class="mb-4">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-cyan-700 dark:text-cyan-400">Purchaser
                Portal</p>
            <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">Market Purchase Hub</h1>
        </div>

        {{-- Date Selector --}}
        <div
            class="mb-6 bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 p-4 shadow-sm">
            <form action="{{ route('purchaser.dashboard') }}" method="GET"
                class="flex items-center justify-between gap-4">
                <span class="text-xs font-black text-slate-700 dark:text-slate-300 uppercase tracking-wider">Business
                    Date:</span>
                <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()"
                    class="text-sm font-bold text-cyan-600 dark:text-cyan-400 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 focus:outline-none focus:ring-1 focus:ring-cyan-500 cursor-pointer">
            </form>
        </div>

        {{-- Success / Error Alerts --}}
        @if(session('success'))
            <div
                class="mb-6 bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-900/55 text-emerald-850 dark:text-emerald-455 text-xs font-bold px-4 py-3.5 rounded-2xl flex items-center gap-2.5 shadow-sm">
                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if($errors->any())
            <div
                class="mb-6 bg-rose-50 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-900/55 text-rose-800 dark:text-rose-450 text-xs font-bold px-4 py-3.5 rounded-2xl shadow-sm">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Tab Content Containers --}}

        {{-- TAB 1: Daily Order --}}
        <div id="tab-daily-order-content" class="tab-pane space-y-4">
            @php
                $pendingRequirementsCount = collect($requirements)->where('status', 'pending')->count();
                $partialRequirementsCount = collect($requirements)->where('status', 'partial')->count();
                $fullRequirementsCount = collect($requirements)->where('status', 'full')->count();
            @endphp
            <div class="flex justify-between items-center mb-2">
                <h2 class="text-sm font-black uppercase text-slate-500 dark:text-slate-400 tracking-wider">Daily
                    Requirements</h2>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="openAdHocPurchaseModal()"
                        class="bg-cyan-600 hover:bg-cyan-700 text-white font-black text-xs px-3 py-1.5 rounded-xl transition border-0 cursor-pointer shadow-sm">
                        + Buy Other
                    </button>
                    <span
                        class="text-xs font-bold bg-slate-100 dark:bg-slate-800 px-2.5 py-1 rounded-full text-slate-700 dark:text-slate-300">
                        {{ count($requirements) }} Products
                    </span>
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m1.85-5.15a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input
                        id="daily-product-search"
                        type="search"
                        placeholder="Search products..."
                        class="w-full rounded-2xl border border-slate-200 bg-slate-50 py-3 pl-10 pr-4 text-sm font-bold text-slate-800 outline-none transition focus:border-cyan-400 focus:bg-white dark:border-slate-800 dark:bg-slate-950 dark:text-white"
                        oninput="filterPurchaserRequirements()"
                    >
                </div>

                <div class="mt-3 grid grid-cols-3 gap-2">
                    <button type="button" id="requirements-filter-pending" onclick="setRequirementFilter('pending')"
                        class="requirements-filter-btn rounded-2xl bg-cyan-500 px-3 py-2 text-xs font-black text-white shadow-sm transition">
                        Pending ({{ $pendingRequirementsCount }})
                    </button>
                    <button type="button" id="requirements-filter-partial" onclick="setRequirementFilter('partial')"
                        class="requirements-filter-btn rounded-2xl bg-slate-100 px-3 py-2 text-xs font-black text-slate-600 transition dark:bg-slate-800 dark:text-slate-300">
                        Partial ({{ $partialRequirementsCount }})
                    </button>
                    <button type="button" id="requirements-filter-full" onclick="setRequirementFilter('full')"
                        class="requirements-filter-btn rounded-2xl bg-slate-100 px-3 py-2 text-xs font-black text-slate-600 transition dark:bg-slate-800 dark:text-slate-300">
                        Full ({{ $fullRequirementsCount }})
                    </button>
                </div>
            </div>

            @forelse($requirements as $req)
                @php
                    $isCompleted = $req['remaining'] <= 0;
                @endphp
                <div
                    class="product-card purchaser-requirement-card bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 shadow-sm flex items-center justify-between gap-4 cursor-pointer hover:border-cyan-500/50 transition-colors"
                    data-status="{{ $req['status'] }}"
                    data-search="{{ strtolower($req['product_name'].' '.$req['sku']) }}"
                    onclick="openPurchaseModal('{{ addslashes($req['product_name']) }}', {{ $req['product_id'] }}, {{ $req['remaining'] }}, '{{ $req['unit'] }}', '{{ addslashes(json_encode($req['shop_split'])) }}')">
                    <div class="flex items-center gap-3">
                        <div
                            class="w-1.5 h-10 rounded-full {{ $isCompleted ? 'bg-emerald-500' : ($req['total_bought'] > 0 ? 'bg-amber-400' : 'bg-slate-300 dark:bg-slate-750') }}">
                        </div>
                        <div>
                            <h4 class="font-bold text-sm text-slate-900 dark:text-white">{{ $req['product_name'] }}</h4>
                            <span
                            class="block text-[10px] text-slate-400 font-bold uppercase tracking-wider">{{ $req['sku'] }}</span>
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <span class="text-xs font-black text-slate-900 dark:text-white block">
                            {{ number_format($req['total_needed'], 2) }} {{ $req['unit'] }} Needed
                        </span>
                        @if($req['total_bought'] > 0)
                            <span class="block text-[9px] text-emerald-600 dark:text-emerald-400 font-bold">
                                Logged: {{ number_format($req['total_bought'], 2) }} {{ $req['unit'] }}
                            </span>
                        @else
                            <span class="block text-[9px] text-slate-400 font-bold">Not bought yet</span>
                        @endif
                    </div>
                </div>
            @empty
                <div
                    class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 text-center shadow-sm">
                    <p class="text-xs text-slate-500 font-semibold">No requirements found for this date.</p>
                </div>
            @endforelse

            <div id="daily-product-empty-state" class="hidden bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 text-center shadow-sm">
                <p class="text-xs font-semibold text-slate-500">No products match this filter.</p>
            </div>
        </div>

        {{-- TAB 2: Bought Items (Draft List) --}}
        <div id="tab-bought-content" class="tab-pane hidden space-y-4">
            <div class="flex justify-between items-center mb-2">
                <h2 class="text-sm font-black uppercase text-slate-500 dark:text-slate-400 tracking-wider">Draft Logged
                    Purchases</h2>
                <span
                    class="text-xs font-bold bg-cyan-50 dark:bg-cyan-950 text-cyan-700 dark:text-cyan-400 px-2.5 py-1 rounded-full border border-cyan-100 dark:border-cyan-900/50">
                    {{ count($draftItemsList) }} Items
                </span>
            </div>

            @if(count($draftItemsList) > 0)
                {{-- Submit to PM Button --}}
                <div
                    class="bg-gradient-to-r from-cyan-600 to-indigo-600 text-white rounded-3xl p-4 shadow-md flex items-center justify-between gap-4">
                    <div>
                        <h4 class="font-black text-sm">Finish & Submit?</h4>
                        <p class="text-[10px] text-cyan-100 font-medium">Send all logged draft purchases to the Purchase
                            Manager.</p>
                    </div>
                    <form action="{{ route('purchaser.purchase.submit') }}" method="POST">
                        @csrf
                        <input type="hidden" name="date" value="{{ $date }}">
                        <button type="submit"
                            class="inline-flex min-w-[9.75rem] items-center justify-center rounded-xl bg-white px-4 py-2.5 text-xs font-black leading-none text-slate-950 shadow-sm transition hover:bg-slate-50 border-0 cursor-pointer">
                            Submit to Manager
                        </button>
                    </form>
                </div>

                {{-- Draft Items List --}}
                <div class="space-y-3">
                    @foreach($draftItemsList as $draft)
                        <div
                            class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 shadow-sm flex items-center justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <h4 class="font-bold text-sm text-slate-900 dark:text-white truncate">
                                    {{ $draft['product_name'] }}</h4>
                                <span
                                    class="block text-[10px] text-slate-400 font-bold uppercase tracking-wider">{{ $draft['sku'] }}
                                    &middot; Supplier: {{ $draft['supplier_name'] }}</span>
                                <span class="block text-xs font-bold text-cyan-600 dark:text-cyan-400 mt-1">
                                    {{ number_format($draft['quantity'], 2) }} {{ $draft['unit'] }} @ INR
                                    {{ number_format($draft['unit_price'], 2) }}/{{ $draft['unit'] }}
                                </span>
                            </div>
                            <form action="{{ route('purchaser.purchase.draft.delete', $draft['id']) }}" method="POST"
                                onsubmit="return confirm('Are you sure you want to remove this logged item?')" class="shrink-0">
                                @csrf
                                <button type="submit"
                                    class="p-2 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/20 rounded-xl transition border-0 bg-transparent cursor-pointer">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @else
                <div
                    class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 text-center shadow-sm">
                    <p class="text-xs text-slate-550 font-semibold">No draft purchases logged yet. Click products on the
                        "Daily Order" tab to start buying.</p>
                </div>
            @endif
        </div>

        {{-- TAB 3: Remaining to Buy --}}
        <div id="tab-remaining-content" class="tab-pane hidden space-y-4">
            <div class="flex justify-between items-center mb-2">
                <h2 class="text-sm font-black uppercase text-slate-500 dark:text-slate-400 tracking-wider">Remaining to
                    Procure</h2>
                <span
                    class="text-xs font-bold bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 px-2.5 py-1 rounded-full border border-amber-100 dark:border-amber-900/50">
                    {{ count(collect($requirements)->filter(fn($r) => $r['remaining'] > 0)) }} Left
                </span>
            </div>

            @forelse(collect($requirements)->filter(fn($r) => $r['remaining'] > 0) as $req)
                <div class="product-card bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 shadow-sm flex items-center justify-between gap-4 cursor-pointer hover:border-cyan-500/50 transition-colors"
                    onclick="openPurchaseModal('{{ addslashes($req['product_name']) }}', {{ $req['product_id'] }}, {{ $req['remaining'] }}, '{{ $req['unit'] }}', '{{ addslashes(json_encode($req['shop_split'])) }}')">
                    <div class="flex items-center gap-3">
                        <div class="w-1.5 h-10 rounded-full bg-amber-400"></div>
                        <div>
                            <h4 class="font-bold text-sm text-slate-900 dark:text-white">{{ $req['product_name'] }}</h4>
                            <span
                                class="block text-[10px] text-slate-400 font-bold uppercase tracking-wider">{{ $req['sku'] }}</span>
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <span class="text-xs font-black text-rose-600 dark:text-rose-450 block">
                            {{ number_format($req['remaining'], 2) }} {{ $req['unit'] }} left
                        </span>
                        <span class="block text-[9px] text-slate-450">Needed:
                            {{ number_format($req['total_needed'], 2) }}</span>
                    </div>
                </div>
            @empty
                <div
                    class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 text-center shadow-sm">
                    <div
                        class="w-12 h-12 rounded-full bg-emerald-50 dark:bg-emerald-950/30 flex items-center justify-center mx-auto mb-3 border border-emerald-100 dark:border-emerald-900/40">
                        <svg class="w-6 h-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 class="font-bold text-sm text-slate-900 dark:text-white">All Procured!</h3>
                    <p class="text-xs text-slate-500 mt-1">There are no remaining items to buy for this date.</p>
                </div>
            @endforelse
        </div>

        {{-- TAB 4: History (Submitted Submissions) --}}
        <div id="tab-history-content" class="tab-pane hidden space-y-4">
            <div class="flex justify-between items-center mb-2">
                <h2 class="text-sm font-black uppercase text-slate-500 dark:text-slate-400 tracking-wider">Submitted
                    Purchase Summary</h2>
                <span class="text-xs font-bold bg-slate-105 dark:bg-slate-800 text-slate-600 px-2.5 py-1 rounded-full">
                    {{ count($submittedPurchaseSummaries) }} Products
                </span>
            </div>

            @forelse($submittedPurchaseSummaries as $summary)
                <div
                    class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 shadow-sm relative overflow-hidden">
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <h4 class="font-bold text-sm text-slate-900 dark:text-white">{{ $summary['product_name'] }}</h4>
                            <span class="block text-[10px] text-slate-400 font-bold uppercase tracking-wider">
                                {{ $summary['sku'] }} &middot; {{ implode(', ', $summary['supplier_names']) }}
                            </span>
                        </div>
                        <span
                            class="text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full border {{ $summary['status'] === 'approved' ? 'bg-emerald-100 text-emerald-800 border-emerald-250' : 'bg-amber-100 text-amber-800 border-amber-250' }}">
                            {{ $summary['status'] === 'approved' ? 'Approved' : 'Awaiting approval' }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-3 border-t border-slate-100 dark:border-slate-800/80 pt-3">
                        <div class="rounded-2xl bg-slate-50 p-3 dark:bg-slate-950/30">
                            <span class="block text-[10px] font-black uppercase tracking-wider text-slate-400">Submitted Qty</span>
                            <span class="mt-1 block text-sm font-black text-slate-900 dark:text-white">
                                {{ number_format($summary['total_quantity'], 2) }} {{ $summary['unit'] }}
                            </span>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-3 dark:bg-slate-950/30">
                            <span class="block text-[10px] font-black uppercase tracking-wider text-slate-400">Avg Price</span>
                            <span class="mt-1 block text-sm font-black text-cyan-600 dark:text-cyan-400">
                                INR {{ number_format($summary['average_price'], 2) }}/{{ $summary['unit'] }}
                            </span>
                        </div>
                    </div>
                </div>
            @empty
                <div
                    class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 text-center shadow-sm">
                    <p class="text-xs text-slate-500 font-semibold">No submitted purchases for this date.</p>
                </div>
            @endforelse
        </div>
    </div>

    {{-- Easy Record Purchase Drawer/Modal --}}
    <div id="purchase-helper-modal"
        class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 hidden"
        aria-labelledby="modal-title" role="dialog" aria-modal="true">
        {{-- Backdrop --}}
        <div class="fixed inset-0 bg-slate-950/65 backdrop-blur-sm transition-opacity" onclick="closePurchaseModal()">
        </div>

        {{-- Modal Content --}}
        <div
            class="relative bg-white dark:bg-slate-900 rounded-t-[2rem] sm:rounded-3xl w-full sm:max-w-md overflow-hidden shadow-2xl transition-all border border-slate-200 dark:border-slate-800 flex flex-col max-h-[90vh] sm:max-h-[80vh]">
            {{-- Header --}}
            <div
                class="px-5 py-4 border-b border-slate-100 dark:border-slate-800/80 flex justify-between items-center bg-slate-50 dark:bg-slate-950/50">
                <div class="min-w-0 flex-1">
                    <span
                        class="text-[9px] font-black uppercase tracking-[0.18em] text-cyan-600 dark:text-cyan-400">Record
                        Buy</span>
                    <h3 class="font-black text-sm text-slate-900 dark:text-white mt-0.5 truncate" id="modal-product-name">Product Name</h3>
                    <div id="modal-product-select-container" class="hidden mt-1.5">
                        <select id="modal-product-select" class="w-full text-xs font-bold bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-slate-800 dark:text-white focus:outline-none focus:ring-1 focus:ring-cyan-500 cursor-pointer">
                            <option value="">Choose Product...</option>
                            @foreach($products as $prod)
                                <option value="{{ $prod->id }}" data-unit="{{ $prod->unit }}">{{ $prod->name }} ({{ $prod->sku }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <button type="button" onclick="closePurchaseModal()"
                    class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-850 hover:text-slate-700 dark:hover:text-white transition cursor-pointer shrink-0 ml-2">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="p-5 overflow-y-auto space-y-4 flex-1">
                {{-- Shop Demands list --}}
                <div>
                    <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider block mb-2">Shop Order
                        Splits</span>
                    <div class="space-y-2 bg-slate-50 dark:bg-slate-950/30 border border-slate-150 dark:border-slate-850/80 p-3 rounded-2xl max-h-[150px] overflow-y-auto"
                        id="modal-shops-list">
                        {{-- Injected dynamically --}}
                    </div>
                </div>

                {{-- Record Form --}}
                <div class="pt-3 border-t border-slate-100 dark:border-slate-800/85">
                    <form action="{{ route('purchaser.purchase.draft.record') }}" method="POST" class="space-y-3">
                        @csrf
                        <input type="hidden" name="date" value="{{ $date }}">
                        <input type="hidden" name="product_id" id="modal-product-id">

                        <div>
                            <label
                                class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Supplier:</label>
                            @php
                                $singleSupplierId = count($suppliers) === 1 ? $suppliers->first()?->id : null;
                                $selectedSupplierId = old('supplier_id', $singleSupplierId);
                            @endphp
                            @if($singleSupplierId)
                                <input type="hidden" name="supplier_id" value="{{ $singleSupplierId }}">
                            @endif
                            <div class="relative">
                                <select name="supplier_id" required
                                    class="w-full appearance-none rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 pr-10 text-xs font-bold text-slate-800 transition focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-100 dark:border-slate-800 dark:bg-slate-950 dark:text-white dark:focus:border-cyan-500 dark:focus:ring-cyan-950 cursor-pointer"
                                    @disabled(count($suppliers) === 1)>
                                    <option value="" @selected($selectedSupplierId === null)>Select Supplier</option>
                                    @foreach($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}" @selected((string) $selectedSupplierId === (string) $supplier->id)>
                                            {{ $supplier->name }} ({{ $supplier->category }})
                                        </option>
                                    @endforeach
                                </select>
                                <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                            @if(count($suppliers) === 1)
                                <p class="mt-1 text-[10px] font-semibold text-slate-400">
                                    Defaulted to the only available supplier.
                                </p>
                            @endif
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label
                                    class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Bought
                                    Qty (<span id="modal-qty-unit">kg</span>):</label>
                                <div class="relative">
                                    <input type="number" step="0.01" min="0.01" name="quantity" id="modal-qty-input"
                                        required
                                        class="w-full text-xs font-bold bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2.5 text-slate-800 dark:text-white focus:outline-none focus:ring-1 focus:ring-cyan-500">
                                    <button type="button" onclick="fillModalFull()"
                                        class="absolute right-1 top-1 bottom-1 px-2.5 bg-slate-200 hover:bg-slate-300 dark:bg-slate-850 dark:hover:bg-slate-800 border-0 rounded-lg text-[8px] font-black uppercase text-slate-700 dark:text-slate-350 transition cursor-pointer">
                                        Full
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label
                                    class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Price
                                    (INR):</label>
                                <input type="number" step="0.01" min="0.01" name="unit_price" required
                                    placeholder="0.00"
                                    class="w-full text-xs font-bold bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2.5 text-slate-800 dark:text-white focus:outline-none focus:ring-1 focus:ring-cyan-500">
                            </div>
                        </div>

                        <button type="submit"
                            class="w-full bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black py-3 rounded-xl transition border-0 cursor-pointer shadow-sm flex items-center justify-center gap-1">
                            Save Item to Bought List
                        </button>
                    </form>
                </div>
            </div>

            {{-- Footer --}}
            <div
                class="px-5 py-4 border-t border-slate-100 dark:border-slate-800/80 bg-slate-50 dark:bg-slate-950/30 flex justify-end">
                <button type="button" onclick="closePurchaseModal()"
                    class="bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold px-4 py-2.5 rounded-xl transition cursor-pointer">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    {{-- Bottom Tab Nav Bar — pill-capsule style matching design screenshot --}}
    <div class="fixed inset-x-0 bottom-5 z-50 px-5 lg:hidden">
        <nav id="purchaser-bottom-nav"
            class="mx-auto flex h-[60px] max-w-md items-center gap-1 rounded-[2rem] border border-slate-100 bg-white/96 px-2 shadow-[0_8px_40px_rgba(0,0,0,0.10),0_2px_12px_rgba(0,0,0,0.06)] backdrop-blur-xl dark:bg-slate-900/96 dark:border-slate-800">

            {{-- Tab 1: Daily Order --}}
            <button onclick="switchTab('daily-order')" id="tab-daily-order-btn" type="button"
                class="purchaser-tab-btn relative flex flex-1 items-center justify-center gap-1.5 h-11 rounded-[1.25rem] border-0 bg-transparent transition-all duration-250 ease-in-out cursor-pointer font-bold">
                <svg class="purchaser-tab-icon h-[18px] w-[18px] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2" />
                </svg>
                <span class="purchaser-tab-label text-[11px] font-black whitespace-nowrap hidden">Daily Order</span>
            </button>

            {{-- Tab 2: Bought --}}
            <button onclick="switchTab('bought')" id="tab-bought-btn" type="button"
                class="purchaser-tab-btn relative flex flex-1 items-center justify-center gap-1.5 h-11 rounded-[1.25rem] border-0 bg-transparent transition-all duration-250 ease-in-out cursor-pointer font-bold">
                <span class="relative shrink-0">
                    <svg class="purchaser-tab-icon h-[18px] w-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                    @if(count($draftItemsList) > 0)
                        <span class="absolute -top-1.5 -right-2 bg-rose-500 text-white text-[8px] font-black h-3.5 w-3.5 rounded-full flex items-center justify-center leading-none">
                            {{ count($draftItemsList) }}
                        </span>
                    @endif
                </span>
                <span class="purchaser-tab-label text-[11px] font-black whitespace-nowrap hidden">Bought</span>
            </button>

            {{-- Tab 3: Remaining --}}
            <button onclick="switchTab('remaining')" id="tab-remaining-btn" type="button"
                class="purchaser-tab-btn relative flex flex-1 items-center justify-center gap-1.5 h-11 rounded-[1.25rem] border-0 bg-transparent transition-all duration-250 ease-in-out cursor-pointer font-bold">
                <svg class="purchaser-tab-icon h-[18px] w-[18px] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <span class="purchaser-tab-label text-[11px] font-black whitespace-nowrap hidden">Remaining</span>
            </button>

            {{-- Tab 4: History --}}
            <button onclick="switchTab('history')" id="tab-history-btn" type="button"
                class="purchaser-tab-btn relative flex flex-1 items-center justify-center gap-1.5 h-11 rounded-[1.25rem] border-0 bg-transparent transition-all duration-250 ease-in-out cursor-pointer font-bold">
                <svg class="purchaser-tab-icon h-[18px] w-[18px] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="purchaser-tab-label text-[11px] font-black whitespace-nowrap hidden">History</span>
            </button>

        </nav>
    </div>

    {{-- Hide standard layouts app mobile bottom nav on this page --}}
    <style>
        /* Hide the shared layout bottom nav on the purchaser dashboard — we use our own tab nav */
        #layout-mobile-nav {
            display: none !important;
        }
    </style>

    <script>
        let currentRemainingQty = 0;
        let currentRequirementFilter = 'pending';

        function fillModalFull() {
            const qtyInput = document.getElementById('modal-qty-input');
            if (qtyInput) {
                qtyInput.value = currentRemainingQty;
            }
        }

        function setRequirementFilter(filter) {
            currentRequirementFilter = filter;

            document.querySelectorAll('.requirements-filter-btn').forEach((button) => {
                button.classList.remove('bg-cyan-500', 'text-white', 'shadow-sm');
                button.classList.add('bg-slate-100', 'text-slate-600', 'dark:bg-slate-800', 'dark:text-slate-300');
            });

            const activeButton = document.getElementById(`requirements-filter-${filter}`);
            if (activeButton) {
                activeButton.classList.add('bg-cyan-500', 'text-white', 'shadow-sm');
                activeButton.classList.remove('bg-slate-100', 'text-slate-600', 'dark:bg-slate-800', 'dark:text-slate-300');
            }

            filterPurchaserRequirements();
        }

        function filterPurchaserRequirements() {
            const searchInput = document.getElementById('daily-product-search');
            const query = (searchInput?.value || '').trim().toLowerCase();
            const cards = document.querySelectorAll('.purchaser-requirement-card');
            let visibleCount = 0;

            cards.forEach((card) => {
                const status = card.getAttribute('data-status');
                const search = card.getAttribute('data-search') || '';
                const matchesFilter = status === currentRequirementFilter;
                const matchesQuery = query === '' || search.includes(query);
                const isVisible = matchesFilter && matchesQuery;

                card.classList.toggle('hidden', !isVisible);

                if (isVisible) {
                    visibleCount++;
                }
            });

            const emptyState = document.getElementById('daily-product-empty-state');
            if (emptyState) {
                emptyState.classList.toggle('hidden', visibleCount > 0);
            }
        }

        function switchTab(tabId) {
            // Hide all tab panes
            document.querySelectorAll('.tab-pane').forEach(el => el.classList.add('hidden'));

            // Show selected pane
            const selectedPane = document.getElementById('tab-' + tabId + '-content');
            if (selectedPane) selectedPane.classList.remove('hidden');

            // Update pill-capsule active state on all buttons
            const tabs = ['daily-order', 'bought', 'remaining', 'history'];
            tabs.forEach(id => {
                const btn = document.getElementById('tab-' + id + '-btn');
                if (!btn) return;
                const icon = btn.querySelector('.purchaser-tab-icon');
                const label = btn.querySelector('.purchaser-tab-label');

                if (id === tabId) {
                    // Active: filled cyan pill + show label
                    btn.classList.add('bg-cyan-500', 'text-white', 'shadow-sm');
                    btn.classList.remove('text-slate-400', 'bg-transparent');
                    if (icon) {
                        icon.classList.add('text-white');
                        icon.classList.remove('text-slate-400');
                    }
                    if (label) {
                        label.classList.remove('hidden');
                        label.classList.add('text-white');
                        label.classList.remove('text-slate-400');
                    }
                } else {
                    // Inactive: icon only, no label
                    btn.classList.remove('bg-cyan-500', 'text-white', 'shadow-sm');
                    btn.classList.add('text-slate-400', 'bg-transparent');
                    if (icon) {
                        icon.classList.remove('text-white');
                        icon.classList.add('text-slate-400');
                    }
                    if (label) {
                        label.classList.add('hidden');
                        label.classList.remove('text-white');
                        label.classList.add('text-slate-400');
                    }
                }
            });

            // Update sidebar buttons active state client-side
            document.querySelectorAll('.purchaser-sidebar-tab-btn').forEach(link => {
                const linkTab = link.getAttribute('data-tab');
                if (linkTab === tabId) {
                    link.classList.add('bg-cyan-400', 'text-slate-950', 'shadow-sm');
                    link.classList.remove('text-slate-300', 'hover:bg-white/5', 'hover:text-white');
                } else {
                    link.classList.remove('bg-cyan-400', 'text-slate-950', 'shadow-sm');
                    link.classList.add('text-slate-300', 'hover:bg-white/5', 'hover:text-white');
                }
            });

            // Persist active tab across reloads
            localStorage.setItem('purchaser_active_tab', tabId);

            // Update URL query parameter without reloading
            const url = new URL(window.location);
            if (url.searchParams.get('tab') !== tabId) {
                url.searchParams.set('tab', tabId);
                window.history.pushState({}, '', url);
            }
        }

        // Restore tab state on page load
        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            const activeTab = tabParam || localStorage.getItem('purchaser_active_tab') || 'daily-order';
            switchTab(activeTab);
            setRequirementFilter('pending');

            // Handle ad-hoc product selection changes
            const productSelect = document.getElementById('modal-product-select');
            if (productSelect) {
                productSelect.addEventListener('change', (e) => {
                    const selectedOption = e.target.options[e.target.selectedIndex];
                    const productId = e.target.value;
                    const unit = selectedOption ? selectedOption.getAttribute('data-unit') : 'kg';
                    
                    document.getElementById('modal-product-id').value = productId;
                    document.getElementById('modal-qty-unit').textContent = unit || 'kg';
                });
            }
        });

        // Open easy record purchase modal for regular items
        function openPurchaseModal(productName, productId, remainingQty, unit, splitJson) {
            const modal = document.getElementById('purchase-helper-modal');
            
            document.getElementById('modal-product-name').textContent = productName;
            document.getElementById('modal-product-name').classList.remove('hidden');
            document.getElementById('modal-product-select-container').classList.add('hidden');
            
            document.getElementById('modal-product-id').value = productId;
            document.getElementById('modal-qty-unit').textContent = unit;

            currentRemainingQty = remainingQty;
            document.getElementById('modal-qty-input').value = remainingQty;

            // Render shop splits
            const listEl = document.getElementById('modal-shops-list');
            listEl.innerHTML = '';
            try {
                const split = JSON.parse(splitJson);
                if (split && split.length > 0) {
                    split.forEach(item => {
                        const row = document.createElement('div');
                        row.className = 'flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2 last:border-b-0 last:pb-0';

                        const shopName = document.createElement('span');
                        shopName.className = 'font-bold text-[11px] text-slate-700 dark:text-slate-350';
                        shopName.textContent = item.shop_name;

                        const qty = document.createElement('span');
                        qty.className = 'font-black text-[11px] text-slate-900 dark:text-white bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 px-2 py-0.5 rounded-lg';
                        qty.textContent = `${parseFloat(item.quantity).toFixed(2)} ${unit}`;

                        row.appendChild(shopName);
                        row.appendChild(qty);
                        listEl.appendChild(row);
                    });
                } else {
                    listEl.innerHTML = `<p class="text-xs text-slate-500 text-center py-2">No shop demands consolidated.</p>`;
                }
            } catch (e) {
                console.error(e);
                listEl.innerHTML = `<p class="text-xs text-red-500 text-center py-2">Error loading split data.</p>`;
            }

            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        // Open easy record purchase modal for ad-hoc items
        function openAdHocPurchaseModal() {
            const modal = document.getElementById('purchase-helper-modal');
            
            document.getElementById('modal-product-name').classList.add('hidden');
            document.getElementById('modal-product-select-container').classList.remove('hidden');
            
            document.getElementById('modal-product-id').value = '';
            document.getElementById('modal-qty-unit').textContent = 'kg';

            currentRemainingQty = 0;
            document.getElementById('modal-qty-input').value = '';

            const productSelect = document.getElementById('modal-product-select');
            if (productSelect) {
                productSelect.value = '';
            }

            const listEl = document.getElementById('modal-shops-list');
            listEl.innerHTML = `<p class="text-xs text-slate-500 text-center py-2">No shop demands consolidated (Ad-hoc purchase).</p>`;

            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function closePurchaseModal() {
            const modal = document.getElementById('purchase-helper-modal');
            if (modal) {
                modal.classList.add('hidden');
            }
            document.body.classList.remove('overflow-hidden');
        }
    </script>
</x-layouts.app>
