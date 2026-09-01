@extends('admin.cashbook.layouts.app')

@section('title', 'Shop Daily Bill Changes — Green Leaf Cashbook')

@section('header_title')
    <i data-lucide="receipt-text" class="w-5 h-5 text-emerald-600"></i> Shop Bill Changes
@endsection

@section('header_subtitle')
    Daily shop-by-shop bill changes, price modifications, and physical inventory outcomes.
@endsection

@section('content')
    <div class="mx-auto max-w-5xl space-y-6"
         x-data="shopBillChanges()"
         x-init="init()">

        <!-- Page Header & Day-First Controls -->
        <div class="flex flex-col gap-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Shop Daily Bill Changes</h1>
                    <p class="text-xs font-bold text-slate-500 mt-0.5">Track what changed in each shop's bills and physical inventory outcomes</p>
                </div>

                <!-- Day-First Quick Controls -->
                <div class="inline-flex items-center gap-1 rounded-2xl bg-slate-100 p-1 shadow-inner self-start sm:self-auto">
                    <a href="{{ route('admin.cashbook.bill-changes', array_filter(['timeframe' => 'today', 'shop_id' => $shopId, 'search' => $search])) }}"
                       class="rounded-xl px-3.5 py-2 text-xs font-black transition-all {{ $timeframe === 'today' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                        Today
                    </a>
                    <a href="{{ route('admin.cashbook.bill-changes', array_filter(['timeframe' => 'yesterday', 'shop_id' => $shopId, 'search' => $search])) }}"
                       class="rounded-xl px-3.5 py-2 text-xs font-black transition-all {{ $timeframe === 'yesterday' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                        Yesterday
                    </a>
                </div>
            </div>

            <!-- Filter & Search Bar -->
            <div class="rounded-3xl border border-slate-200/90 bg-white p-4 shadow-xs space-y-3">
                <form method="GET" action="{{ route('admin.cashbook.bill-changes') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                    <input type="hidden" name="timeframe" value="custom">

                    <!-- Search Input -->
                    <div class="relative">
                        <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                        <input type="text"
                               name="search"
                               value="{{ $search }}"
                               placeholder="Search shop, invoice #, product..."
                               class="w-full pl-8 pr-3 py-2 text-xs font-medium rounded-xl bg-slate-50 border border-slate-200 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                    </div>

                    <!-- Shop Dropdown -->
                    <div>
                        <select name="shop_id" onchange="this.form.submit()" class="w-full px-3 py-2 text-xs font-semibold rounded-xl bg-slate-50 border border-slate-200 text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 cursor-pointer">
                            <option value="">All Shops</option>
                            @foreach($availableShops as $shop)
                                <option value="{{ $shop->id }}" {{ (string) $shopId === (string) $shop->id ? 'selected' : '' }}>
                                    {{ $shop->name }} ({{ $shop->code }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Date Picker & Reset -->
                    <div class="flex items-center gap-2">
                        <x-cashbook.previous-month-button mode="as_of" size="sm" label="{{ now()->startOfMonth()->subDay()->format('M') }}" />
                        <div class="relative flex-1">
                            <i data-lucide="calendar" class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                            <input type="date"
                                   name="date"
                                   value="{{ $selectedDate }}"
                                   onchange="this.form.submit()"
                                   class="w-full pl-8 pr-3 py-2 text-xs font-bold rounded-xl bg-slate-50 border border-slate-200 text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 cursor-pointer">
                        </div>

                        @if($search || $shopId || $timeframe !== 'today')
                            <a href="{{ route('admin.cashbook.bill-changes') }}"
                               title="Reset filters"
                               class="flex-none p-2 text-slate-400 hover:text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition">
                                <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <!-- SECTION 1: SHOP SUMMARY LIST (CARDS) -->
        <div class="space-y-3">
            <div class="flex items-center justify-between px-1">
                <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider flex items-center gap-2">
                    <i data-lucide="store" class="w-4 h-4 text-emerald-600"></i>
                    Shop Daily Summary — {{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }}
                </h2>
                <span class="text-xs font-bold text-slate-500">
                    {{ count($shopSummaries) }} shop(s) with bills
                </span>
            </div>

            @if(empty($shopSummaries))
                <div class="p-10 text-center bg-white rounded-3xl border border-slate-200/90 shadow-xs">
                    <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-2">
                        <i data-lucide="store" class="w-6 h-6"></i>
                    </div>
                    <h3 class="text-sm font-black text-slate-900">No Shop Bills on {{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }}</h3>
                    <p class="text-xs text-slate-500 mt-1">No shop invoices exist for this date matching your filter.</p>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($shopSummaries as $sSummary)
                        <div class="bg-white rounded-3xl border border-slate-200/90 p-4 sm:p-5 shadow-xs hover:shadow-md transition-all flex flex-col justify-between space-y-4">
                            <div>
                                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                                    <div>
                                        <h3 class="text-sm font-black text-slate-900">{{ $sSummary['shop_name'] }}</h3>
                                        <span class="font-mono text-[11px] font-bold text-slate-400">{{ $sSummary['shop_code'] }}</span>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">Final Amount</span>
                                        <span class="text-sm font-black text-emerald-800 font-mono">₹{{ number_format($sSummary['total_final_amount'], 2) }}</span>
                                    </div>
                                </div>

                                <div class="grid grid-cols-3 gap-2 text-center pt-3">
                                    <div class="bg-slate-50 p-2 rounded-xl border border-slate-100">
                                        <span class="text-[10px] font-extrabold uppercase text-slate-400 block">Bills</span>
                                        <span class="text-xs font-black text-slate-800">{{ $sSummary['total_bills'] }}</span>
                                    </div>
                                    <div class="bg-amber-50/70 p-2 rounded-xl border border-amber-100">
                                        <span class="text-[10px] font-extrabold uppercase text-amber-700 block">Changed</span>
                                        <span class="text-xs font-black text-amber-900">{{ $sSummary['changed_bills'] }}</span>
                                    </div>
                                    <div class="bg-emerald-50/70 p-2 rounded-xl border border-emerald-100">
                                        <span class="text-[10px] font-extrabold uppercase text-emerald-700 block">Finalized</span>
                                        <span class="text-xs font-black text-emerald-900">{{ $sSummary['finalized_bills'] }}</span>
                                    </div>
                                </div>
                            </div>

                            <button type="button"
                                    @click="openShopDayModal({{ $sSummary['shop_id'] }}, '{{ addslashes($sSummary['shop_name']) }}')"
                                    class="w-full py-2 px-3 rounded-xl bg-slate-900 text-white text-xs font-black hover:bg-slate-800 transition flex items-center justify-center gap-1.5 shadow-xs">
                                <span>View Day Bills &amp; Changes</span>
                                <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- SECTION 2: DAY-WISE BILLS TABLE / CARDS -->
        <div class="space-y-3 pt-2">
            <div class="flex items-center justify-between px-1">
                <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider flex items-center gap-2">
                    <i data-lucide="receipt" class="w-4 h-4 text-emerald-600"></i>
                    All Bills on {{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }}
                </h2>
                <span class="text-xs font-bold text-slate-500">
                    Showing {{ $invoices->total() }} bill(s)
                </span>
            </div>

            @if($invoices->isEmpty())
                <div class="p-8 text-center bg-white rounded-3xl border border-slate-200/90 shadow-xs">
                    <p class="text-xs text-slate-500">No invoices recorded for this date.</p>
                </div>
            @else
                <div class="grid grid-cols-1 gap-3">
                    @foreach($invoices as $inv)
                        @php
                            $invActs = $activitiesByInvoice->get($inv->id, collect());
                            $hasChanges = $invActs->isNotEmpty() || $inv->shortage_total > 0 || $inv->discount_total > 0;
                            $latestAct = $invActs->first();
                            $actProps = $latestAct?->properties ?? [];
                            $summaryText = data_get($actProps, 'auto_change_summary') ?: ($inv->delivery_note ?: 'No specific notes recorded.');
                        @endphp
                        <div class="bg-white rounded-3xl border border-slate-200/90 p-4 sm:p-5 shadow-xs hover:shadow-md transition-all space-y-3">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5 pb-3 border-b border-slate-100">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-2xl bg-emerald-50 text-emerald-700 flex items-center justify-center font-black text-xs flex-shrink-0">
                                        <i data-lucide="receipt" class="w-4 h-4"></i>
                                    </div>
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-sm font-black text-slate-900">{{ $inv->shop?->name ?? 'Shop' }}</h3>
                                            <span class="font-mono text-xs font-black text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-lg border border-emerald-200">
                                                {{ $inv->invoice_number }}
                                            </span>
                                            @if($inv->isFinalized())
                                                <span class="inline-flex items-center rounded-lg bg-emerald-100 px-2 py-0.5 text-[10px] font-black text-emerald-800">
                                                    FINALIZED
                                                </span>
                                            @else
                                                <span class="inline-flex items-center rounded-lg bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-700 uppercase">
                                                    {{ str_replace('_', ' ', $inv->status) }}
                                                </span>
                                            @endif
                                            @if($hasChanges)
                                                <span class="inline-flex items-center rounded-lg bg-amber-100 px-2 py-0.5 text-[10px] font-black text-amber-800">
                                                    {{ $invActs->count() ?: 1 }} CHANGE(S)
                                                </span>
                                            @endif
                                        </div>
                                        <div class="text-[11px] font-bold text-slate-400 mt-0.5 flex items-center gap-1.5 flex-wrap">
                                            <span>Finalized by {{ $inv->finalizedBy?->name ?? data_get($actProps, 'actor.name') ?? 'Admin' }}</span>
                                            <span>•</span>
                                            <span>{{ $inv->finalized_at?->format('H:i') ?? $inv->updated_at?->format('H:i') }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between sm:justify-end gap-3 self-end sm:self-auto w-full sm:w-auto">
                                    <div class="text-right">
                                        <span class="text-[10px] font-extrabold uppercase text-slate-400 block">Final Amount</span>
                                        <span class="text-sm font-black text-slate-900 font-mono">₹{{ number_format((float) $inv->final_total, 2) }}</span>
                                    </div>
                                    <button type="button"
                                            @click="openInvoiceModal({{ $inv->id }})"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-900 text-white text-xs font-black hover:bg-slate-800 transition shadow-xs">
                                        <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                        <span>View Changes</span>
                                    </button>
                                </div>
                            </div>

                            @if($hasChanges && $summaryText)
                                <div class="text-xs font-mono text-slate-600 bg-slate-50 p-2.5 rounded-xl border border-slate-100 whitespace-pre-line leading-relaxed">
{{ $summaryText }}
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="mt-4">
                    {{ $invoices->links() }}
                </div>
            @endif
        </div>

        <!-- POPUP MODAL / BOTTOM SHEET (WHAT CHANGED & INVENTORY OUTCOME) -->
        <div x-show="modalOpen"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-2xl w-full p-5 sm:p-6 space-y-4 max-h-[90vh] overflow-y-auto"
                 @click.away="modalOpen = false">

                <!-- Modal Header -->
                <div class="flex items-start justify-between pb-3 border-b border-slate-100">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-black text-slate-900" x-text="modalTitle"></h3>
                            <span class="font-mono text-xs font-black text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200"
                                  x-text="currentInvoice ? currentInvoice.invoice_number : ''"></span>
                        </div>
                        <p class="text-xs text-slate-500 font-bold mt-0.5" x-text="'Date: ' + '{{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }}'"></p>
                    </div>
                    <button type="button" @click="modalOpen = false" class="text-slate-400 hover:text-slate-600 p-1">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <!-- Loading State -->
                <template x-if="loading">
                    <div class="py-12 text-center space-y-2">
                        <div class="inline-block animate-spin text-emerald-600">
                            <i data-lucide="loader-2" class="w-6 h-6"></i>
                        </div>
                        <p class="text-xs font-bold text-slate-500">Loading bill audit details...</p>
                    </div>
                </template>

                <!-- Loaded Details -->
                <template x-if="!loading && modalInvoices.length > 0">
                    <div class="space-y-5">
                        <template x-for="inv in modalInvoices" :key="inv.id">
                            <div class="rounded-2xl border border-slate-200 bg-slate-50/50 p-4 space-y-3.5">
                                <!-- Invoice Header inside modal -->
                                <div class="flex items-center justify-between pb-2 border-b border-slate-200/80">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-black text-slate-900" x-text="inv.invoice_number"></span>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider"
                                              :class="inv.status === 'FINALIZED' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-800'"
                                              x-text="inv.status"></span>
                                        <template x-if="inv.is_admin_on_behalf">
                                            <span class="px-2 py-0.5 rounded text-[9px] font-extrabold uppercase bg-purple-100 text-purple-800">
                                                FINALIZED BY ADMIN ON BEHALF OF SHOP
                                            </span>
                                        </template>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-xs font-black text-emerald-900 font-mono" x-text="'₹' + Number(inv.final_total).toFixed(2)"></span>
                                    </div>
                                </div>

                                <!-- Inventory Resolutions / Changes -->
                                <template x-if="inv.inventory_resolutions && inv.inventory_resolutions.length > 0">
                                    <div class="space-y-2.5">
                                        <template x-for="res in inv.inventory_resolutions" :key="res.product_name">
                                            <div class="bg-white rounded-xl border border-slate-200 p-3 space-y-2">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-xs font-black text-slate-900" x-text="res.product_name"></span>
                                                    <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase"
                                                          :class="res.resolution === 'return_to_warehouse' ? 'bg-emerald-100 text-emerald-800' : (res.resolution === 'wastage' ? 'bg-rose-100 text-rose-800' : (res.resolution === 'deduct_extra' ? 'bg-blue-100 text-blue-800' : 'bg-amber-100 text-amber-800'))"
                                                          x-text="res.resolution === 'return_to_warehouse' ? 'RETURNED TO WAREHOUSE ' + res.resolution_qty + ' ' + (res.unit || 'KG') : (res.resolution === 'wastage' ? 'WASTAGE ' + res.resolution_qty + ' ' + (res.unit || 'KG') : (res.resolution === 'deduct_extra' ? 'EXTRA STOCK DEDUCTED ' + res.resolution_qty + ' ' + (res.unit || 'KG') : 'ALREADY ACCOUNTED ' + res.resolution_qty + ' ' + (res.unit || 'KG')))">
                                                    </span>
                                                </div>

                                                <!-- Loaded vs Final Comparison -->
                                                <div class="grid grid-cols-3 gap-2 bg-slate-50 p-2 rounded-lg text-center text-xs">
                                                    <div>
                                                        <span class="text-[9px] font-extrabold uppercase text-slate-400 block">Loaded</span>
                                                        <span class="font-mono font-bold text-slate-700" x-text="(res.loaded_qty ?? '—') + ' ' + (res.unit || 'KG')"></span>
                                                    </div>
                                                    <div>
                                                        <span class="text-[9px] font-extrabold uppercase text-slate-400 block">Final Bill</span>
                                                        <span class="font-mono font-black text-slate-900" x-text="(res.final_qty ?? '—') + ' ' + (res.unit || 'KG')"></span>
                                                    </div>
                                                    <div>
                                                        <span class="text-[9px] font-extrabold uppercase text-slate-400 block">Difference</span>
                                                        <span class="font-mono font-black"
                                                              :class="(res.final_qty - res.loaded_qty) < 0 ? 'text-rose-700' : 'text-emerald-700'"
                                                              x-text="((res.final_qty - res.loaded_qty) > 0 ? '+' : '') + (res.final_qty - res.loaded_qty) + ' ' + (res.unit || 'KG')"></span>
                                                    </div>
                                                </div>

                                                <div class="text-xs text-slate-600 flex flex-wrap justify-between gap-1 pt-1">
                                                    <div>
                                                        <span class="font-bold text-slate-700">Reason:</span>
                                                        <span x-text="res.reason ? res.reason.replace(/_/g, ' ') : 'Loadout Mistake'"></span>
                                                    </div>
                                                    <template x-if="res.item_note">
                                                        <div class="italic text-slate-500" x-text="'&quot;' + res.item_note + '&quot;'"></div>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                <!-- Auto Change Summary or Price/Discount Details -->
                                <template x-if="inv.auto_change_summary">
                                    <div class="bg-white p-3 rounded-xl border border-slate-200 text-[11px] font-mono text-slate-700 whitespace-pre-line leading-relaxed"
                                         x-text="inv.auto_change_summary"></div>
                                </template>

                                <!-- Notes & Metadata -->
                                <div class="text-xs text-slate-600 space-y-1 pt-1 border-t border-slate-200/80">
                                    <template x-if="inv.overall_note">
                                        <div>
                                            <span class="font-bold text-slate-700">Overall Invoice Note:</span>
                                            <span x-text="'&quot;' + inv.overall_note + '&quot;'"></span>
                                        </div>
                                    </template>
                                    <div class="text-[11px] text-slate-400 flex items-center justify-between pt-1">
                                        <span x-text="'Finalized by ' + (inv.finalized_by || 'Admin') + ' • ' + (inv.finalized_at || '—')"></span>
                                        <a :href="'/purchasing/shop-invoices/' + inv.id" class="font-bold text-emerald-700 hover:underline">View Full Bill</a>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>

    </div>

    <script>
        function shopBillChanges() {
            return {
                modalOpen: false,
                loading: false,
                modalTitle: '',
                currentInvoice: null,
                modalInvoices: [],

                init() {
                    if (window.lucide) { lucide.createIcons(); }
                },

                async openShopDayModal(shopId, shopName) {
                    this.modalOpen = true;
                    this.loading = true;
                    this.modalTitle = shopName;
                    this.currentInvoice = null;
                    this.modalInvoices = [];

                    try {
                        const res = await fetch(`{{ route('admin.cashbook.bill-changes.shop-day') }}?shop_id=${shopId}&date={{ $selectedDate }}`);
                        const json = await res.json();
                        this.modalInvoices = json.data || [];
                    } catch (e) {
                        console.error(e);
                    } finally {
                        this.loading = false;
                        this.$nextTick(() => { if (window.lucide) { lucide.createIcons(); } });
                    }
                },

                async openInvoiceModal(invoiceId) {
                    this.modalOpen = true;
                    this.loading = true;
                    this.modalTitle = 'Invoice Changes Audit';
                    this.modalInvoices = [];

                    try {
                        const res = await fetch(`{{ route('admin.cashbook.bill-changes.shop-day') }}?invoice_id=${invoiceId}&date={{ $selectedDate }}`);
                        const json = await res.json();
                        this.modalInvoices = json.data || [];
                        if (this.modalInvoices.length > 0) {
                            this.currentInvoice = this.modalInvoices[0];
                            this.modalTitle = this.currentInvoice.shop_name;
                        }
                    } catch (e) {
                        console.error(e);
                    } finally {
                        this.loading = false;
                        this.$nextTick(() => { if (window.lucide) { lucide.createIcons(); } });
                    }
                }
            };
        }
    </script>
@endsection
