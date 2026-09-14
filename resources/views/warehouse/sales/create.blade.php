<x-layouts.app title="Warehouse Sale - New Sale">
@php
    $todayDate = \Carbon\Carbon::today()->toDateString();
    $pageDate = $date ?? $todayDate;
@endphp

<style>
.ws-modal { display:none; position:fixed; inset:0; z-index:999; background:rgba(15,23,42,0.55); align-items:center; justify-content:center; padding:1rem; }
.ws-modal.open { display:flex; }
.ws-modal-box { background:#fff; border-radius:1.25rem; width:100%; max-width:28rem; max-height:92vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.18); border:1px solid #e2e8f0; }
.ws-step { display:none; }
.ws-step.active { display:block; }
.ws-item-row { display:grid; grid-template-columns:2rem 1fr 4.5rem 5rem 5rem 2rem; gap:.25rem; align-items:start; padding:.5rem 0; border-bottom:1px solid #f1f5f9; }
.ws-item-row:last-child { border-bottom:none; }
.ws-input { height:1.75rem; width:100%; border:1px solid #e2e8f0; border-radius:.375rem; background:#f8fafc; padding:0 .375rem; font-size:.6875rem; font-weight:700; color:#0f172a; outline:none; }
.ws-input:focus { background:#fff; border-color:#0d9488; }
.ws-select { height:1.75rem; width:100%; border:1px solid #e2e8f0; border-radius:.375rem; background:#f8fafc; padding:0 .25rem; font-size:.6875rem; font-weight:700; color:#0f172a; cursor:pointer; }
.ws-btn-primary { display:inline-flex; align-items:center; justify-content:center; height:2.5rem; padding:0 1.25rem; background:#0d9488; color:#fff; border-radius:.75rem; font-size:.75rem; font-weight:900; border:none; cursor:pointer; transition:background .15s; }
.ws-btn-primary:hover { background:#0f766e; }
.ws-btn-secondary { display:inline-flex; align-items:center; justify-content:center; height:2.5rem; padding:0 1rem; background:#f8fafc; color:#334155; border-radius:.75rem; font-size:.75rem; font-weight:700; border:1px solid #e2e8f0; cursor:pointer; transition:background .15s; }
.ws-btn-secondary:hover { background:#f1f5f9; }
.ws-customer-card { display:flex; align-items:center; justify-content:space-between; padding:.75rem 1rem; border-radius:.875rem; border:2px solid #e2e8f0; cursor:pointer; margin-bottom:.5rem; transition:border-color .15s, background .15s; }
.ws-customer-card:hover { border-color:#cbd5e1; background:#f8fafc; }
.ws-customer-card.selected { border-color:#0d9488; background:#f0fdf4; }
.ws-shop-btn { display:flex; align-items:center; justify-content:space-between; width:100%; padding:.6rem .75rem; border-radius:.625rem; border:1px solid #f1f5f9; cursor:pointer; text-align:left; font-size:.6875rem; font-weight:700; color:#334155; transition:all .15s; background:#fff; }
.ws-shop-btn:hover, .ws-shop-btn.selected { background:#eef2ff; border-color:#a5b4fc; }
.pmeth-btn { height:2rem; border-radius:.5rem; border:1px solid #e2e8f0; background:#f8fafc; color:#334155; font-size:.6875rem; font-weight:900; cursor:pointer; transition:all .15s; }
.pmeth-btn.active { background:#0d9488; color:#fff; border-color:#0d9488; }
.line-total { height:1.75rem; border-radius:.375rem; background:#f1f5f9; padding:0 .375rem; display:flex; align-items:center; justify-content:flex-end; font-family:monospace; font-size:.6875rem; font-weight:900; color:#0f172a; }
</style>

<div class="mx-auto flex w-full max-w-full min-w-0 flex-col gap-3 py-3 sm:px-2 lg:max-w-5xl lg:gap-4 lg:px-6 lg:py-4">

    <!-- Page header -->
    <section class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-[9px] font-black uppercase tracking-[.18em] text-teal-600">Warehouse Sales</p>
                <h1 class="mt-0.5 text-lg font-black text-slate-950">New Sale Bill</h1>
                <p class="mt-0.5 text-xs font-semibold text-slate-600" id="hdr-customer-line">Cash Sales • {{ $pageDate }}</p>
            </div>
            <a href="{{ route('warehouse.sales.index', ['warehouse_id' => $selectedWarehouseId, 'date' => $pageDate]) }}"
               class="inline-flex h-9 items-center justify-center rounded-xl border border-slate-200 px-4 text-xs font-black text-slate-700 hover:bg-slate-50">← Back</a>
        </div>
    </section>

    <!-- Error banner -->
    <div id="error-banner" style="display:none;" class="rounded-2xl border border-rose-200 bg-rose-50 p-3 text-xs font-bold text-rose-800 flex items-center justify-between gap-2">
        <span id="error-text"></span>
        <button onclick="document.getElementById('error-banner').style.display='none'" class="text-rose-600 font-black">✕</button>
    </div>

    <!-- ===== Receipt Card ===== -->
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="relative mx-auto max-w-[36rem] bg-white px-4 py-6 sm:px-6">

            <!-- Ribbon -->
            <div id="ribbon" class="absolute -left-11 top-7 w-40 -rotate-45 py-1 text-center text-xs font-black uppercase tracking-[.14em] text-white bg-teal-600">Received</div>

            <!-- Header -->
            <header class="border-b border-dashed border-slate-400 pb-3 text-center">
                <h2 class="text-xl font-black uppercase tracking-wide text-slate-950">Sale Invoice</h2>
                <p class="mt-1.5 text-base font-black uppercase text-slate-950">Green Leaf ERP</p>
                <p class="mt-0.5 text-[11px] font-semibold text-slate-600">Warehouse Counter Sales</p>
            </header>

            <!-- Meta row -->
            <div class="grid grid-cols-1 gap-3 border-b border-dashed border-slate-400 py-3 sm:grid-cols-2">
                <div class="space-y-1.5 text-[11px] font-bold text-slate-800">
                    <p class="text-[10px] font-black uppercase tracking-[.12em] text-slate-500">Invoice No</p>
                    <p class="font-mono text-xs font-black text-slate-950">Pending (Auto)</p>
                    <div class="flex items-center gap-1.5 pt-0.5">
                        <span class="shrink-0 text-[10px] font-black uppercase text-slate-500">Warehouse:</span>
                        <select id="sel-warehouse" onchange="onWarehouseChange()" class="ws-select flex-1">
                            @foreach($allowedWarehouses as $wh)
                                <option value="{{ $wh->id }}" {{ (int)$wh->id === (int)$selectedWarehouseId ? 'selected' : '' }}>{{ $wh->name }} ({{ $wh->code }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="space-y-1 sm:text-right text-[11px]">
                    <div class="flex items-center sm:justify-end gap-1.5">
                        <span class="text-[10px] font-black uppercase text-slate-500">Date:</span>
                        <input type="date" id="inp-date" value="{{ $pageDate }}" class="ws-input" style="width:auto;">
                    </div>
                    <p class="text-[11px] font-semibold text-slate-600">Cashier: {{ auth()->user()->name }}</p>
                </div>
            </div>

            <!-- Customer -->
            <div class="border-b border-dashed border-slate-400 py-3 text-[11px] text-slate-700">
                <div class="flex items-center justify-between">
                    <p class="font-black uppercase tracking-[.12em] text-slate-500">Customer</p>
                    <button type="button" onclick="openModal('customer-modal')" class="inline-flex items-center gap-1 text-[11px] font-bold text-teal-700 hover:text-teal-900">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Z" /></svg>
                        Change Customer
                    </button>
                </div>
                <div class="mt-1.5 flex items-center gap-2">
                    <p class="text-sm font-black text-slate-950" id="disp-customer-name">Cash Sales</p>
                    <span id="disp-customer-badge" class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase bg-emerald-100 text-emerald-800">Cash Sales</span>
                </div>
                <p id="disp-customer-phone" class="mt-0.5 text-[10px] font-mono font-semibold text-slate-500" style="display:none;"></p>
            </div>

            <!-- Hidden state inputs -->
            <input type="hidden" id="inp-customer-type" value="cash_sales">
            <input type="hidden" id="inp-shop-id" value="">
            <input type="hidden" id="inp-customer-id" value="">
            <input type="hidden" id="inp-customer-phone" value="">

            <!-- Items table header -->
            <div class="border-b border-dashed border-slate-400 py-3">
                <div style="display:grid;grid-template-columns:2rem 1fr 4.5rem 5rem 5rem 2rem;gap:.25rem;padding-bottom:.5rem;border-bottom:1px dashed #cbd5e1;">
                    <span class="text-[10px] font-black uppercase text-slate-600">SN</span>
                    <span class="text-[10px] font-black uppercase text-slate-600">Item</span>
                    <span class="text-[10px] font-black uppercase text-slate-600 text-right">Qty</span>
                    <span class="text-[10px] font-black uppercase text-slate-600 text-right">Price</span>
                    <span class="text-[10px] font-black uppercase text-slate-600 text-right">Amt</span>
                    <span></span>
                </div>

                <!-- Item rows rendered here by JS -->
                <div id="items-container"></div>

                <!-- Actions -->
                <div class="mt-3 flex items-center justify-between">
                    <button type="button" onclick="addRow()" class="inline-flex items-center gap-1 rounded-lg border border-teal-200 bg-teal-50 px-3 py-1.5 text-[11px] font-black text-teal-700 hover:bg-teal-100">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        + Add Row
                    </button>
                    <button type="button" onclick="openProductModal()" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-100 px-3 py-1.5 text-[11px] font-black text-slate-700 hover:bg-slate-200">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                        Quick Search
                    </button>
                </div>
            </div>

            <!-- Totals -->
            <div class="border-b border-dashed border-slate-400 py-3 space-y-2 text-[11px] font-bold text-slate-800">
                <div class="flex items-center justify-between">
                    <span class="text-slate-600">Subtotal</span>
                    <span class="font-mono font-black text-slate-950">₹<span id="disp-subtotal">0.00</span></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-600">Discount (₹)</span>
                    <input type="number" id="inp-discount" value="0" min="0" step="0.01" oninput="recalcTotals()" class="ws-input text-right" style="width:6rem;">
                </div>
                <div class="flex items-center justify-between border-t border-slate-200 pt-2">
                    <span class="text-base font-black uppercase tracking-wider text-slate-950">Total Bill</span>
                    <span class="font-mono text-base font-black text-teal-700">₹<span id="disp-total">0.00</span></span>
                </div>
            </div>

            <footer class="pt-3 text-center">
                <p class="text-xs font-black text-slate-800">Thank You</p>
            </footer>
        </div>
    </section>

    <!-- Notes -->
    <div class="mx-auto w-full max-w-[36rem] rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
        <label for="inp-notes" class="text-[10px] font-bold uppercase tracking-[.1em] text-slate-500">Notes / Remarks (Optional)</label>
        <input id="inp-notes" type="text" placeholder="Optional note..." class="mt-1 h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none focus:border-teal-500">
    </div>

    <!-- Sticky CTA -->
    <div class="sticky bottom-4 z-20 mx-auto w-full max-w-[36rem] rounded-2xl border border-slate-200 bg-white/95 p-2 shadow-lg backdrop-blur">
        <div class="flex items-center gap-2">
            <a href="{{ route('warehouse.sales.index', ['warehouse_id' => $selectedWarehouseId, 'date' => $pageDate]) }}"
               class="inline-flex h-11 w-20 items-center justify-center rounded-xl border border-slate-200 text-xs font-black text-slate-700 hover:bg-slate-50">Back</a>
            <button type="button" onclick="openPaymentModal()"
                    class="inline-flex h-11 flex-1 items-center justify-center rounded-xl bg-teal-600 px-4 text-xs font-black text-white hover:bg-teal-500">
                Save &amp; Receive &nbsp;(₹<span id="cta-total">0.00</span>)
            </button>
        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- CUSTOMER MODAL                                                      -->
<!-- ================================================================ -->
<div id="customer-modal" class="ws-modal" onclick="if(event.target===this)closeModal('customer-modal')">
    <div class="ws-modal-box">

        <!-- Step: main -->
        <div id="cstep-main" class="ws-step active p-5 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h3 class="text-sm font-black text-slate-900">Select Customer</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Choose customer type for this sale</p>
                </div>
                <button onclick="closeModal('customer-modal')" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100">✕</button>
            </div>
            <div class="space-y-2">
                <!-- Cash Sales -->
                <div class="ws-customer-card" id="ccard-cash" onclick="selectCashSales()">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-black text-slate-900">Cash Sales</span>
                                <span class="text-[9px] font-black uppercase px-1.5 py-0.5 rounded bg-emerald-200 text-emerald-900">Default</span>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5">Anonymous fast cash sale</p>
                        </div>
                    </div>
                    <span class="text-emerald-700 font-black text-xs" id="ccard-cash-check" style="display:none;">✓</span>
                </div>

                <!-- Shop -->
                <div class="ws-customer-card" id="ccard-shop" onclick="showCStep('shop')">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z"/></svg>
                        </div>
                        <div>
                            <span class="text-sm font-black text-slate-900">Shop</span>
                            <p class="text-xs text-slate-500 mt-0.5">Select existing ERP shop</p>
                        </div>
                    </div>
                    <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                </div>

                <!-- Walking -->
                <div class="ws-customer-card" id="ccard-walking" onclick="showCStep('walking')">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
                        </div>
                        <div>
                            <span class="text-sm font-black text-slate-900">Walking Customer</span>
                            <p class="text-xs text-slate-500 mt-0.5">Quick name &amp; phone (not saved)</p>
                        </div>
                    </div>
                    <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                </div>

                <!-- New Customer -->
                <div class="ws-customer-card" onclick="showCStep('new-customer')" style="border-style:dashed;">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-teal-100 text-teal-700 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z"/></svg>
                        </div>
                        <div>
                            <span class="text-sm font-black text-slate-900">+ New Customer</span>
                            <p class="text-xs text-slate-500 mt-0.5">Create &amp; save customer record</p>
                        </div>
                    </div>
                    <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                </div>
            </div>
        </div>

        <!-- Step: shop -->
        <div id="cstep-shop" class="ws-step p-5 space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <button onclick="showCStep('main')" class="inline-flex items-center gap-1 text-xs font-bold text-slate-600 hover:text-slate-900">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg> Back
                </button>
                <h3 class="text-sm font-black text-slate-900">Select Shop</h3>
                <button onclick="closeModal('customer-modal')" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>
            <input type="text" id="shop-search" placeholder="Search shop name..." oninput="filterShops()"
                   class="w-full h-10 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:border-indigo-500 focus:bg-white focus:outline-none">
            <div id="shop-list" class="space-y-1 max-h-64 overflow-y-auto pr-1">
                @foreach($shops as $shop)
                    <button type="button" class="ws-shop-btn"
                            onclick="selectShop({{ $shop->id }}, '{{ addslashes($shop->name) }}', '{{ addslashes($shop->contact_phone ?? '') }}')"
                            data-name="{{ strtolower($shop->name) }}">
                        <div>
                            <span class="text-xs font-bold text-slate-900">{{ $shop->name }}</span>
                            @if($shop->code) <span class="ml-1.5 text-[10px] font-mono bg-slate-100 text-slate-500 px-1.5 rounded">{{ $shop->code }}</span> @endif
                            @if($shop->contact_phone) <div class="text-[10px] font-mono text-slate-400 mt-0.5">{{ $shop->contact_phone }}</div> @endif
                        </div>
                        <svg class="w-4 h-4 text-slate-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                    </button>
                @endforeach
            </div>
        </div>

        <!-- Step: walking -->
        <div id="cstep-walking" class="ws-step p-5 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <button onclick="showCStep('main')" class="inline-flex items-center gap-1 text-xs font-bold text-slate-600 hover:text-slate-900">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg> Back
                </button>
                <h3 class="text-sm font-black text-slate-900">Walking Customer</h3>
                <button onclick="closeModal('customer-modal')" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>
            <div class="space-y-3">
                <div>
                    <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">Name (Optional)</label>
                    <input type="text" id="walking-name" placeholder="Leave blank for 'Walking Customer'"
                           class="w-full h-10 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:border-amber-500 focus:bg-white focus:outline-none">
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase text-slate-500 mb-1">Phone (Optional)</label>
                    <input type="tel" id="walking-phone" placeholder="9876543210"
                           class="w-full h-10 rounded-xl border border-slate-200 bg-slate-50 px-3 font-mono text-xs font-bold text-slate-900 focus:border-amber-500 focus:bg-white focus:outline-none">
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                <button onclick="closeModal('customer-modal')" class="ws-btn-secondary">Cancel</button>
                <button onclick="applyWalkingCustomer()" class="ws-btn-primary" style="background:#d97706;" onmouseover="this.style.background='#b45309'" onmouseout="this.style.background='#d97706'">Use Customer</button>
            </div>
        </div>

        <!-- Step: new-customer -->
        <div id="cstep-new-customer" class="ws-step p-5 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <button onclick="showCStep('main')" class="inline-flex items-center gap-1 text-xs font-bold text-slate-600 hover:text-slate-900">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg> Back
                </button>
                <h3 class="text-sm font-black text-slate-900">Create New Customer</h3>
                <button onclick="closeModal('customer-modal')" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>
            <div id="nc-error" class="hidden rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700"></div>
            <div class="space-y-3">
                <div>
                    <label class="mb-1 block text-[10px] font-black uppercase text-slate-500">Name <span class="text-rose-500">*</span></label>
                    <input type="text" id="nc-name" placeholder="Customer name"
                           class="h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                </div>
                <div>
                    <label class="mb-1 block text-[10px] font-black uppercase text-slate-500">Phone</label>
                    <input type="tel" id="nc-phone" placeholder="Phone number"
                           class="h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 font-mono text-xs font-semibold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
                </div>
            </div>
            <button id="nc-submit-btn" onclick="saveNewCustomer()" class="ws-btn-primary w-full h-9 text-xs">Create &amp; Use Customer</button>
        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- PRODUCT SEARCH MODAL                                               -->
<!-- ================================================================ -->
<div id="product-modal" class="ws-modal" onclick="if(event.target===this)closeModal('product-modal')">
    <div class="ws-modal-box p-5 flex flex-col gap-3" style="max-height:90vh;">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 shrink-0">
            <div>
                <h3 class="text-base font-black text-slate-900">Product Picker</h3>
                <p class="text-xs text-slate-500">Search and tap to add</p>
            </div>
            <button onclick="closeModal('product-modal')" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <input type="text" id="prod-search" placeholder="Search product name or SKU..." oninput="filterProducts()"
               class="h-11 shrink-0 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-bold text-slate-900 focus:border-teal-500 focus:bg-white focus:outline-none">
        <div id="prod-list" class="space-y-1.5 overflow-y-auto flex-1 pr-1"></div>
    </div>
</div>

<!-- ================================================================ -->
<!-- PAYMENT MODAL                                                      -->
<!-- ================================================================ -->
<div id="payment-modal" class="ws-modal" onclick="if(event.target===this)closeModal('payment-modal')">
    <div class="ws-modal-box p-5 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-sm font-black text-slate-950">Receive Payment</h3>
                <p id="pay-subtitle" class="text-[11px] font-semibold text-slate-500 mt-0.5"></p>
            </div>
            <button onclick="closeModal('payment-modal')" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>

        <!-- Total card -->
        <div class="rounded-2xl bg-slate-950 p-3.5 text-white space-y-1">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase text-slate-400">Total Bill</span>
                <span class="font-mono text-base font-black text-teal-300">₹<span id="pay-total">0.00</span></span>
            </div>
            <div id="pay-discount-row" class="flex items-center justify-between text-[11px]" style="display:none!important;">
                <span class="text-slate-400">Discount</span>
                <span class="font-mono text-rose-400 font-bold" id="pay-discount-val"></span>
            </div>
        </div>

        <!-- Payment method -->
        <div>
            <label class="text-[10px] font-black uppercase text-slate-500">How was payment received?</label>
            <div class="mt-1.5 grid grid-cols-3 gap-1.5">
                <button class="pmeth-btn active" id="pmeth-cash" onclick="setPayMethod('cash')">Cash</button>
                <button class="pmeth-btn" id="pmeth-upi" onclick="setPayMethod('upi')">UPI / QR</button>
                <button class="pmeth-btn" id="pmeth-card" onclick="setPayMethod('card')">Card</button>
                <button class="pmeth-btn" id="pmeth-bank" onclick="setPayMethod('bank')">Bank</button>
                <button class="pmeth-btn col-span-2" id="pmeth-credit" onclick="setPayMethod('credit')" style="grid-column:span 2;">Credit (On Account)</button>
            </div>
        </div>

        <!-- Cash holder -->
        <div id="cash-holder-section" class="rounded-2xl border border-slate-200 bg-slate-50 p-3 space-y-2">
            <label class="text-[10px] font-black uppercase text-slate-600">Cash Received By</label>
            <div class="grid grid-cols-2 gap-2">
                <label class="flex items-center gap-2 p-2 rounded-xl border border-teal-300 bg-teal-50 cursor-pointer text-xs font-bold text-teal-900" id="holder-lbl-user">
                    <input type="radio" name="holder_type" value="user" id="holder-radio-user" checked onchange="toggleHolderType()"> Staff
                </label>
                <label class="flex items-center gap-2 p-2 rounded-xl border border-slate-200 bg-white cursor-pointer text-xs font-bold text-slate-600" id="holder-lbl-company">
                    <input type="radio" name="holder_type" value="company" id="holder-radio-company" onchange="toggleHolderType()"> Company
                </label>
            </div>
            <div id="cashier-select-wrap">
                <label class="block text-[10px] font-black uppercase text-slate-600 mb-1">Select Cashier</label>
                <select id="sel-money-holder-user" class="w-full h-9 rounded-lg border border-slate-200 bg-white px-2 text-xs font-bold text-slate-900 focus:outline-none">
                    @foreach($activeUsers as $u)
                        <option value="{{ $u->id }}" {{ (int)$u->id === auth()->id() ? 'selected' : '' }}>
                            {{ $u->name }}{{ (int)$u->id === auth()->id() ? ' (Me)' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <!-- Reference -->
        <div id="reference-section" style="display:none;">
            <label class="text-[10px] font-black uppercase text-slate-500">Payment Reference (Optional)</label>
            <input type="text" id="inp-reference" placeholder="UPI ref, Txn ID…"
                   class="mt-1 h-9 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-900 focus:bg-white focus:outline-none">
        </div>

        <div id="pay-error" class="hidden rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700"></div>

        <button id="pay-submit-btn" onclick="submitSale()"
                class="h-11 w-full rounded-2xl bg-teal-600 text-xs font-black text-white hover:bg-teal-500 transition-all shadow-md flex items-center justify-center gap-2">
            CONFIRM &amp; RECORD RECEIPT
        </button>
    </div>
</div>

<!-- ================================================================ -->
<!-- DATA &amp; JAVASCRIPT                                              -->
<!-- ================================================================ -->
<script>
// ── Data from server ────────────────────────────────────────────────
var WS = {
    warehouseId: {{ (int)$selectedWarehouseId }},
    csrfToken: '{{ csrf_token() }}',
    storeUrl: '{{ route('warehouse.sales.store') }}',
    storeCustomerUrl: '{{ route('warehouse.sales.customers.store') }}',
    searchProductsUrl: '{{ route('warehouse.sales.search-products') }}',
    authUserId: {{ auth()->id() }},
    products: @json($productsWithStock),
    today: '{{ $pageDate }}'
};

// Current state
var state = {
    customerType: 'cash_sales',
    shopId: null,
    customerId: null,
    customerName: 'Cash Sales',
    customerPhone: '',
    paymentMethod: 'cash',
    items: []   // filled on init
};

// ── Modal helpers ─────────────────────────────────────────────────────
function openModal(id) {
    document.getElementById(id).classList.add('open');
}
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
}

// ── Customer modal steps ─────────────────────────────────────────────
function showCStep(name) {
    ['main','shop','walking','new-customer'].forEach(function(s){
        document.getElementById('cstep-' + s).classList.remove('active');
    });
    document.getElementById('cstep-' + name).classList.add('active');
    if (name === 'shop') {
        document.getElementById('shop-search').value = '';
        filterShops();
        setTimeout(function(){ document.getElementById('shop-search').focus(); }, 100);
    }
    if (name === 'new-customer') {
        document.getElementById('nc-name').value = '';
        document.getElementById('nc-phone').value = '';
        hideEl('nc-error');
        setTimeout(function(){ document.getElementById('nc-name').focus(); }, 100);
    }
    if (name === 'walking') {
        setTimeout(function(){ document.getElementById('walking-name').focus(); }, 100);
    }
}

function hideEl(id) {
    var el = document.getElementById(id);
    if (el) el.classList.add('hidden');
}
function showEl(id, txt) {
    var el = document.getElementById(id);
    if (el) { el.classList.remove('hidden'); if (txt !== undefined) el.textContent = txt; }
}

// ── Customer selection ────────────────────────────────────────────────
function selectCashSales() {
    state.customerType = 'cash_sales';
    state.shopId = null;
    state.customerId = null;
    state.customerName = 'Cash Sales';
    state.customerPhone = '';
    applyCustomerUI();
    closeModal('customer-modal');
    showCStep('main');
}

function selectShop(id, name, phone) {
    state.customerType = 'shop';
    state.shopId = id;
    state.customerId = null;
    state.customerName = name;
    state.customerPhone = phone || '';
    applyCustomerUI();
    closeModal('customer-modal');
    showCStep('main');
}

function applyWalkingCustomer() {
    var name = document.getElementById('walking-name').value.trim();
    var phone = document.getElementById('walking-phone').value.trim();
    state.customerType = 'walking_customer';
    state.shopId = null;
    state.customerId = null;
    state.customerName = name || 'Walking Customer';
    state.customerPhone = phone;
    applyCustomerUI();
    closeModal('customer-modal');
    showCStep('main');
}

function applyCustomerUI() {
    // Name + badge
    document.getElementById('disp-customer-name').textContent = state.customerName;
    document.getElementById('hdr-customer-line').textContent = state.customerName + ' • ' + document.getElementById('inp-date').value;

    var badge = document.getElementById('disp-customer-badge');
    if (state.customerType === 'shop') {
        badge.className = 'rounded-full px-2 py-0.5 text-[9px] font-black uppercase bg-indigo-100 text-indigo-800';
        badge.textContent = 'Shop';
    } else if (state.customerType === 'walking_customer') {
        badge.className = 'rounded-full px-2 py-0.5 text-[9px] font-black uppercase bg-amber-100 text-amber-900';
        badge.textContent = 'Walking';
    } else {
        badge.className = 'rounded-full px-2 py-0.5 text-[9px] font-black uppercase bg-emerald-100 text-emerald-800';
        badge.textContent = 'Cash Sales';
    }

    // Phone
    var phoneEl = document.getElementById('disp-customer-phone');
    if (state.customerPhone) {
        phoneEl.textContent = state.customerPhone;
        phoneEl.style.display = '';
    } else {
        phoneEl.style.display = 'none';
    }

    // Hidden inputs
    document.getElementById('inp-customer-type').value = state.customerType;
    document.getElementById('inp-shop-id').value = state.shopId || '';
    document.getElementById('inp-customer-id').value = state.customerId || '';
    document.getElementById('inp-customer-phone').value = state.customerPhone || '';

    // Highlight selected card
    document.getElementById('ccard-cash').classList.toggle('selected', state.customerType === 'cash_sales');
    document.getElementById('ccard-shop').classList.toggle('selected', state.customerType === 'shop');
    document.getElementById('ccard-walking').classList.toggle('selected', state.customerType === 'walking_customer');
    var cashCheck = document.getElementById('ccard-cash-check');
    cashCheck.style.display = state.customerType === 'cash_sales' ? '' : 'none';
}

// ── New customer save ─────────────────────────────────────────────────
function saveNewCustomer() {
    var name = document.getElementById('nc-name').value.trim();
    var phone = document.getElementById('nc-phone').value.trim();
    if (!name) {
        showEl('nc-error', 'Customer name is required.');
        return;
    }
    hideEl('nc-error');
    var btn = document.getElementById('nc-submit-btn');
    btn.disabled = true;
    btn.textContent = 'Saving…';

    fetch(WS.storeCustomerUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': WS.csrfToken },
        body: JSON.stringify({ name: name, phone: phone || null })
    })
    .then(function(r){ return r.json().then(function(d){ return {ok: r.ok, data: d}; }); })
    .then(function(res){
        if (res.ok && res.data.status === 'success') {
            var c = res.data.customer;
            state.customerType = 'walking_customer';
            state.shopId = null;
            state.customerId = c.id;
            state.customerName = c.name;
            state.customerPhone = c.phone || '';
            applyCustomerUI();
            closeModal('customer-modal');
            showCStep('main');
        } else {
            var msg = res.data.message || 'Failed.';
            if (res.data.errors && res.data.errors.name) msg = res.data.errors.name[0];
            showEl('nc-error', msg);
        }
    })
    .catch(function(){ showEl('nc-error', 'Network error.'); })
    .finally(function(){
        btn.disabled = false;
        btn.textContent = 'Create & Use Customer';
    });
}

// ── Shop search ───────────────────────────────────────────────────────
function filterShops() {
    var q = document.getElementById('shop-search').value.toLowerCase();
    var btns = document.getElementById('shop-list').querySelectorAll('.ws-shop-btn');
    btns.forEach(function(b) {
        b.style.display = (!q || b.dataset.name.indexOf(q) !== -1) ? '' : 'none';
    });
}

// ── Product modal ─────────────────────────────────────────────────────
function openProductModal() {
    document.getElementById('prod-search').value = '';
    renderProductList(WS.products);
    openModal('product-modal');
    setTimeout(function(){ document.getElementById('prod-search').focus(); }, 100);
}

function filterProducts() {
    var q = document.getElementById('prod-search').value.toLowerCase().trim();
    var filtered = q
        ? WS.products.filter(function(p){ return (p.name||'').toLowerCase().indexOf(q) !== -1 || (p.sku||'').toLowerCase().indexOf(q) !== -1; })
        : WS.products;
    renderProductList(filtered);
}

function renderProductList(list) {
    var el = document.getElementById('prod-list');
    if (!list.length) {
        el.innerHTML = '<p class="text-center text-xs text-slate-400 p-6">No products found.</p>';
        return;
    }
    el.innerHTML = list.map(function(p) {
        var stockColor = (parseFloat(p.available_stock)||0) <= 0 ? '#dc2626' : '#16a34a';
        var priceStr = p.price > 0 ? ' · ₹' + parseFloat(p.price).toFixed(2) : '';
        return '<button type="button" onclick="quickAddProduct(' + p.id + ')" ' +
               'style="width:100%;display:flex;align-items:center;justify-content:space-between;padding:.75rem;border-radius:.75rem;border:1px solid #f1f5f9;background:#fff;cursor:pointer;text-align:left;margin-bottom:.25rem;" ' +
               'onmouseover="this.style.background=\'#f0fdf4\';this.style.borderColor=\'#6ee7b7\'" onmouseout="this.style.background=\'#fff\';this.style.borderColor=\'#f1f5f9\'">' +
               '<div><div style="display:flex;align-items:center;gap:.5rem">' +
               '<span style="font-size:.75rem;font-weight:900;color:#0f172a">' + escHtml(p.name) + '</span>' +
               (p.sku ? '<span style="font-size:.625rem;font-family:monospace;background:#f1f5f9;color:#64748b;padding:.1rem .35rem;border-radius:.25rem">' + escHtml(p.sku) + '</span>' : '') +
               '</div><div style="font-size:.6rem;color:#64748b;margin-top:.25rem">' +
               'Stock: <strong style="color:' + stockColor + '">' + (p.available_stock||0) + ' ' + (p.unit||'kg') + '</strong>' + priceStr +
               '</div></div>' +
               '<span style="font-size:.6875rem;font-weight:900;color:#0d9488;background:#f0fdf4;padding:.2rem .5rem;border-radius:.5rem;border:1px solid #d1fae5;white-space:nowrap">+ Add</span></button>';
    }).join('');
}

function escHtml(s) {
    return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function quickAddProduct(productId) {
    var p = WS.products.find(function(x){ return x.id === productId; });
    if (!p) return;
    // Find empty row first
    var emptyIdx = -1;
    for (var i = 0; i < state.items.length; i++) {
        if (!state.items[i].product_id) { emptyIdx = i; break; }
    }
    if (emptyIdx >= 0) {
        setRowProduct(emptyIdx, p);
    } else {
        addRow(p);
    }
    closeModal('product-modal');
}

// ── Item rows ─────────────────────────────────────────────────────────
var rowCount = 0;

function addRow(prefillProduct) {
    var item = {
        _id: ++rowCount,
        product_id: prefillProduct ? prefillProduct.id : '',
        grade: 'A',
        qty: 1,
        unit: prefillProduct ? (prefillProduct.unit || 'kg') : 'kg',
        unit_price: prefillProduct ? (prefillProduct.price || 0) : 0,
        available_stock: prefillProduct ? (prefillProduct.available_stock || 0) : 0
    };
    state.items.push(item);
    renderRow(item, state.items.length - 1);
    recalcTotals();
}

function renderRow(item, idx) {
    var container = document.getElementById('items-container');
    var row = document.createElement('div');
    row.className = 'ws-item-row';
    row.id = 'row-' + item._id;

    // Build product options
    var opts = '<option value="">-- Select Product --</option>' +
        WS.products.map(function(p){
            var sel = item.product_id && p.id == item.product_id ? ' selected' : '';
            return '<option value="' + p.id + '"' + sel + '>' + escHtml(p.name) + (p.sku ? ' (' + escHtml(p.sku) + ')' : '') + '</option>';
        }).join('');

    var gradeOpts = ['A','B','C'].map(function(g){
        return '<option value="' + g + '"' + (item.grade===g?' selected':'') + '>' + g + '</option>';
    }).join('');

    var lineTotal = ((parseFloat(item.qty)||0) * (parseFloat(item.unit_price)||0)).toFixed(2);

    row.innerHTML =
        '<span style="padding-top:.4rem;font-size:.6875rem;font-weight:700;color:#64748b">' + (idx+1) + '</span>' +

        '<div style="min-width:0">' +
        '<select class="ws-select" onchange="onRowProductChange(' + item._id + ', this.value)">' + opts + '</select>' +
        '<div style="display:flex;align-items:center;gap:.25rem;margin-top:.2rem;font-size:.5625rem;color:#64748b" id="row-meta-' + item._id + '">' +
        '<span id="row-unit-' + item._id + '" style="font-weight:900;text-transform:uppercase">' + (item.unit||'kg') + '</span>' +
        ' · Stock: <strong id="row-stock-' + item._id + '" style="color:' + ((parseFloat(item.available_stock)||0) <= 0 ? '#dc2626' : '#16a34a') + '">' + (item.available_stock||0) + '</strong>' +
        ' <select style="margin-left:.25rem;border-radius:.25rem;background:#f1f5f9;border:1px solid #e2e8f0;padding:0 .25rem;font-size:.5rem;font-weight:900;cursor:pointer" onchange="onRowGradeChange(' + item._id + ', this.value)">' + gradeOpts + '</select>' +
        '</div></div>' +

        '<input type="number" step="0.001" min="0.001" value="' + item.qty + '" class="ws-input" style="text-align:right" onchange="onRowQtyChange(' + item._id + ', this.value)" oninput="onRowQtyChange(' + item._id + ', this.value)">' +

        '<input type="number" step="0.01" min="0" value="' + item.unit_price + '" class="ws-input" style="text-align:right" onchange="onRowPriceChange(' + item._id + ', this.value)" oninput="onRowPriceChange(' + item._id + ', this.value)">' +

        '<div class="line-total" id="row-total-' + item._id + '">₹' + lineTotal + '</div>' +

        '<button type="button" onclick="removeRow(' + item._id + ')" style="height:1.75rem;display:flex;align-items:center;justify-content:center;color:#fca5a5;cursor:pointer;font-weight:900;font-size:.75rem;background:none;border:none;" onmouseover="this.style.color=\'#ef4444\'" onmouseout="this.style.color=\'#fca5a5\'">✕</button>';

    container.appendChild(row);
    // show meta if product set
    if (!item.product_id) {
        document.getElementById('row-meta-' + item._id).style.display = 'none';
    }
}

function setRowProduct(idx, product) {
    var item = state.items[idx];
    item.product_id = product.id;
    item.unit = product.unit || 'kg';
    item.unit_price = product.price || 0;
    item.available_stock = product.available_stock || 0;
    // Re-render that row
    var oldRow = document.getElementById('row-' + item._id);
    if (oldRow) oldRow.remove();
    // Temporarily remove from array, re-insert at same position
    state.items.splice(idx, 1);
    state.items.splice(idx, 0, item);
    renderRow(item, idx);
    // Renumber all rows
    renumberRows();
    recalcTotals();
}

function onRowProductChange(rowId, productId) {
    var item = state.items.find(function(it){ return it._id === rowId; });
    if (!item) return;
    var p = WS.products.find(function(x){ return String(x.id) === String(productId); });
    if (p) {
        item.product_id = p.id;
        item.unit = p.unit || 'kg';
        item.unit_price = p.price || 0;
        item.available_stock = p.available_stock || 0;
        document.getElementById('row-unit-' + rowId).textContent = item.unit;
        var stockEl = document.getElementById('row-stock-' + rowId);
        stockEl.textContent = item.available_stock;
        stockEl.style.color = (parseFloat(item.available_stock)||0) <= 0 ? '#dc2626' : '#16a34a';
        document.getElementById('row-meta-' + rowId).style.display = '';
        // Update price input
        var priceInput = document.getElementById('row-' + rowId)
            ? document.getElementById('row-' + rowId).querySelectorAll('input')[1] : null;
        if (priceInput) priceInput.value = item.unit_price;
    } else {
        item.product_id = '';
        document.getElementById('row-meta-' + rowId).style.display = 'none';
    }
    recalcRowTotal(rowId);
    recalcTotals();
}

function onRowQtyChange(rowId, val) {
    var item = state.items.find(function(it){ return it._id === rowId; });
    if (item) { item.qty = parseFloat(val) || 0; }
    recalcRowTotal(rowId);
    recalcTotals();
}

function onRowPriceChange(rowId, val) {
    var item = state.items.find(function(it){ return it._id === rowId; });
    if (item) { item.unit_price = parseFloat(val) || 0; }
    recalcRowTotal(rowId);
    recalcTotals();
}

function onRowGradeChange(rowId, val) {
    var item = state.items.find(function(it){ return it._id === rowId; });
    if (item) item.grade = val;
}

function recalcRowTotal(rowId) {
    var item = state.items.find(function(it){ return it._id === rowId; });
    var el = document.getElementById('row-total-' + rowId);
    if (item && el) {
        el.textContent = '₹' + ((parseFloat(item.qty)||0) * (parseFloat(item.unit_price)||0)).toFixed(2);
    }
}

function removeRow(rowId) {
    if (state.items.length <= 1) return;
    state.items = state.items.filter(function(it){ return it._id !== rowId; });
    var row = document.getElementById('row-' + rowId);
    if (row) row.remove();
    renumberRows();
    recalcTotals();
}

function renumberRows() {
    var rows = document.getElementById('items-container').querySelectorAll('.ws-item-row');
    rows.forEach(function(r, i){
        var sn = r.querySelector('span');
        if (sn) sn.textContent = i + 1;
    });
}

function recalcTotals() {
    var subtotal = state.items.reduce(function(s, it){
        return s + ((parseFloat(it.qty)||0) * (parseFloat(it.unit_price)||0));
    }, 0);
    var discount = parseFloat(document.getElementById('inp-discount').value) || 0;
    var total = Math.max(0, subtotal - discount);

    document.getElementById('disp-subtotal').textContent = subtotal.toFixed(2);
    document.getElementById('disp-total').textContent = total.toFixed(2);
    document.getElementById('cta-total').textContent = total.toFixed(2);
}

// ── Warehouse change ──────────────────────────────────────────────────
function onWarehouseChange() {
    var whId = document.getElementById('sel-warehouse').value;
    WS.warehouseId = parseInt(whId);
    fetch(WS.searchProductsUrl + '?warehouse_id=' + whId, { headers: { 'Accept': 'application/json' } })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.status === 'success') {
                WS.products = d.data || [];
                // Rebuild every row's select to only show in-stock products for new warehouse
                state.items.forEach(function(item){
                    var row = document.getElementById('row-' + item._id);
                    if (!row) return;
                    var sel = row.querySelector('select');
                    if (!sel) return;
                    // Rebuild options
                    sel.innerHTML = '<option value="">-- Select Product --</option>' +
                        WS.products.map(function(p){
                            var selected = item.product_id && p.id == item.product_id ? ' selected' : '';
                            return '<option value="' + p.id + '"' + selected + '>' + escHtml(p.name) + (p.sku ? ' (' + escHtml(p.sku) + ')' : '') + '</option>';
                        }).join('');
                    // If current product no longer has stock, clear it
                    if (item.product_id) {
                        var p = WS.products.find(function(x){ return String(x.id) === String(item.product_id); });
                        if (p) {
                            item.available_stock = p.available_stock;
                            var stockEl = document.getElementById('row-stock-' + item._id);
                            if (stockEl) {
                                stockEl.textContent = p.available_stock;
                                stockEl.style.color = (parseFloat(p.available_stock)||0) <= 0 ? '#dc2626' : '#16a34a';
                            }
                        } else {
                            // product no longer in stock for this warehouse — clear row
                            item.product_id = '';
                            item.available_stock = 0;
                            item.unit_price = 0;
                            var metaEl = document.getElementById('row-meta-' + item._id);
                            if (metaEl) metaEl.style.display = 'none';
                            recalcRowTotal(item._id);
                        }
                    }
                });
                recalcTotals();
            }
        }).catch(function(e){ console.error(e); });
}

// ── Payment modal ─────────────────────────────────────────────────────
function setPayMethod(method) {
    state.paymentMethod = method;
    ['cash','upi','card','bank','credit'].forEach(function(m){
        document.getElementById('pmeth-' + m).classList.toggle('active', m === method);
    });
    document.getElementById('cash-holder-section').style.display = method === 'cash' ? '' : 'none';
    document.getElementById('reference-section').style.display = method !== 'cash' ? '' : 'none';
    document.getElementById('ribbon').textContent = method === 'credit' ? 'Credit' : 'Received';
    document.getElementById('ribbon').style.background = method === 'credit' ? '#d97706' : '#0d9488';
}

function toggleHolderType() {
    var isUser = document.getElementById('holder-radio-user').checked;
    document.getElementById('cashier-select-wrap').style.display = isUser ? '' : 'none';
    document.getElementById('holder-lbl-user').style.borderColor = isUser ? '#5eead4' : '#e2e8f0';
    document.getElementById('holder-lbl-user').style.background = isUser ? '#f0fdf4' : '#fff';
    document.getElementById('holder-lbl-user').style.color = isUser ? '#134e4a' : '#475569';
    document.getElementById('holder-lbl-company').style.borderColor = !isUser ? '#5eead4' : '#e2e8f0';
    document.getElementById('holder-lbl-company').style.background = !isUser ? '#f0fdf4' : '#fff';
    document.getElementById('holder-lbl-company').style.color = !isUser ? '#134e4a' : '#475569';
}

function openPaymentModal() {
    var err = validate();
    if (err) { showError(err); return; }

    var discount = parseFloat(document.getElementById('inp-discount').value) || 0;
    var subtotal = state.items.reduce(function(s,it){ return s + ((parseFloat(it.qty)||0)*(parseFloat(it.unit_price)||0)); }, 0);
    var total = Math.max(0, subtotal - discount);

    document.getElementById('pay-total').textContent = total.toFixed(2);
    document.getElementById('pay-subtitle').textContent = state.customerName + ' · ' + document.getElementById('inp-date').value;

    var discRow = document.getElementById('pay-discount-row');
    if (discount > 0) {
        discRow.style.removeProperty('display');
        document.getElementById('pay-discount-val').textContent = '-₹' + discount.toFixed(2);
    } else {
        discRow.style.setProperty('display','none','important');
    }

    hideEl('pay-error');
    openModal('payment-modal');
}

function validate() {
    var whId = document.getElementById('sel-warehouse').value;
    if (!whId) return 'Please select a selling warehouse.';
    if (state.customerType === 'shop' && !state.shopId) return 'Please select a shop.';
    for (var i = 0; i < state.items.length; i++) {
        var it = state.items[i];
        if (!it.product_id) return 'Row #' + (i+1) + ': Please select a product.';
        var q = parseFloat(it.qty) || 0;
        if (q <= 0) return 'Row #' + (i+1) + ': Quantity must be > 0.';
    }
    var subtotal = state.items.reduce(function(s,it){ return s + ((parseFloat(it.qty)||0)*(parseFloat(it.unit_price)||0)); }, 0);
    var discount = parseFloat(document.getElementById('inp-discount').value) || 0;
    if (Math.max(0, subtotal - discount) <= 0) return 'Total must be greater than zero.';
    return null;
}

function showError(msg) {
    document.getElementById('error-text').textContent = msg;
    document.getElementById('error-banner').style.display = '';
}

// ── Submit sale ───────────────────────────────────────────────────────
function submitSale() {
    hideEl('pay-error');
    var btn = document.getElementById('pay-submit-btn');
    btn.disabled = true;
    btn.textContent = 'Processing…';

    var isHolderUser = document.getElementById('holder-radio-user').checked;
    var discount = parseFloat(document.getElementById('inp-discount').value) || 0;

    var payload = {
        warehouse_id: parseInt(document.getElementById('sel-warehouse').value),
        business_date: document.getElementById('inp-date').value,
        customer_type: state.customerType,
        shop_id: state.shopId ? parseInt(state.shopId) : null,
        customer_id: state.customerId ? parseInt(state.customerId) : null,
        customer_name: state.customerName,
        customer_phone: state.customerPhone || null,
        discount: discount,
        notes: document.getElementById('inp-notes').value.trim() || null,
        payment_method: state.paymentMethod,
        money_holder_type: state.paymentMethod === 'cash' ? (isHolderUser ? 'user' : 'company') : 'company',
        money_holder_user_id: (state.paymentMethod === 'cash' && isHolderUser)
            ? parseInt(document.getElementById('sel-money-holder-user').value) : null,
        company_account_id: null,
        reference: document.getElementById('inp-reference') ? document.getElementById('inp-reference').value.trim() || null : null,
        items: state.items.map(function(it){
            return {
                product_id: parseInt(it.product_id),
                grade: it.grade || 'A',
                qty: parseFloat(it.qty),
                unit: it.unit,
                unit_price: parseFloat(it.unit_price)
            };
        })
    };

    fetch(WS.storeUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': WS.csrfToken },
        body: JSON.stringify(payload)
    })
    .then(function(r){ return r.json().then(function(d){ return {ok: r.ok, data: d}; }); })
    .then(function(res){
        if (res.ok && res.data.status === 'success' && res.data.redirect_url) {
            window.location.href = res.data.redirect_url;
        } else {
            var msg = res.data.message || 'Failed to create sale.';
            if (res.data.errors) {
                var k = Object.keys(res.data.errors)[0];
                if (k && res.data.errors[k][0]) msg = res.data.errors[k][0];
            }
            showEl('pay-error', msg);
            btn.disabled = false;
            btn.textContent = 'CONFIRM & RECORD RECEIPT';
        }
    })
    .catch(function(e){
        showEl('pay-error', 'Network error: ' + e.message);
        btn.disabled = false;
        btn.textContent = 'CONFIRM & RECORD RECEIPT';
    });
}

// ── Date header sync ──────────────────────────────────────────────────
document.getElementById('inp-date').addEventListener('change', function(){
    document.getElementById('hdr-customer-line').textContent = state.customerName + ' • ' + this.value;
});

// ── Keyboard shortcuts ─────────────────────────────────────────────────
document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') {
        ['customer-modal','product-modal','payment-modal'].forEach(function(id){
            closeModal(id);
        });
    }
});

// ── Init ──────────────────────────────────────────────────────────────
(function init() {
    // Set today's date
    document.getElementById('inp-date').value = WS.today;
    // Start with one empty row
    addRow();
    // Init toggles
    setPayMethod('cash');
    applyCustomerUI();
})();
</script>
</x-layouts.app>
