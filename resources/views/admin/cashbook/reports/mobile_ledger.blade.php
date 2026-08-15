@extends('admin.cashbook.layouts.app')

@section('title', ($currentShop->name ?: 'Shop #' . $currentShop->shop_id) . ' — Mobile Ledger')

@section('header_title')
    <i data-lucide="smartphone" class="w-5 h-5 text-emerald-600"></i> Mobile Shop Ledger
@endsection

@section('header_subtitle')
    Mobile-optimized ledger layout for fast performance.
@endsection

@section('content')
    <div class="mx-auto max-w-md space-y-4" x-data="mobileLedgerApp()" x-init="init()">
        <!-- Header / Presets Card -->
        <section class="rounded-2xl border border-slate-200 bg-white p-3.5 shadow-xs">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <div class="min-w-0">
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.16em] text-slate-800 border border-slate-200">{{ $currentShop->code ?: ('SHP-' . $currentShop->shop_id) }}</span>
                    <h1 class="text-sm font-black text-slate-900 truncate mt-0.5">{{ $currentShop->name }}</h1>
                </div>
                <div class="flex items-center gap-1">
                    <a href="{{ route('admin.cashbook.reports.hub', ['timeframe' => $timeframe, 'start_date' => $startDate, 'end_date' => $endDate]) }}" class="rounded-lg border border-slate-200 bg-slate-50 p-1.5 text-slate-600 transition hover:bg-slate-100" title="Back to Overview">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    </a>
                </div>
            </div>

            <!-- Date Range Controls -->
            <div class="mt-3">
                <div class="flex items-center gap-1 rounded-xl border border-slate-200 bg-slate-50 p-1">
                    <button type="button" @click="setPreset('today')" class="flex-1 rounded-lg py-1 text-center text-[10px] font-black transition" :class="timeframe === 'today' ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'">Today</button>
                    <button type="button" @click="setPreset('weekly')" class="flex-1 rounded-lg py-1 text-center text-[10px] font-black transition" :class="timeframe === 'weekly' ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'">Week</button>
                    <button type="button" @click="setPreset('monthly')" class="flex-1 rounded-lg py-1 text-center text-[10px] font-black transition" :class="timeframe === 'monthly' ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'">Month</button>
                    <button type="button" @click="setPreset('custom')" class="flex-1 rounded-lg py-1 text-center text-[10px] font-black transition" :class="timeframe === 'custom' ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'">Custom</button>
                    
                    <!-- Calendar Jump to Date Picker -->
                    <label class="relative flex h-6 w-8 items-center justify-center cursor-pointer rounded-lg text-slate-600 hover:bg-slate-200 transition-all shrink-0" title="Jump to Specific Date">
                        <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
                        <input
                            type="date"
                            class="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                            @change="jumpToDate($event.target.value)"
                        >
                    </label>
                </div>

                <div x-show="timeframe === 'custom'" class="mt-2.5 flex items-center gap-1.5" x-cloak>
                    <input type="date" x-model="startDate" class="h-8 flex-1 rounded-lg border border-slate-200 bg-slate-50 px-2 text-xs font-bold text-slate-900">
                    <span class="text-xs font-bold text-slate-400">to</span>
                    <input type="date" x-model="endDate" class="h-8 flex-1 rounded-lg border border-slate-200 bg-slate-50 px-2 text-xs font-bold text-slate-900">
                    <button type="button" @click="applyCustomDates()" class="h-8 rounded-lg bg-emerald-600 px-3 text-xs font-bold text-white transition hover:bg-emerald-500">Apply</button>
                </div>
            </div>
        </section>

        <!-- 4 Summary Cards in 2x2 Grid (Sales, Expense, GL Bill, Net P/L) -->
        <section class="grid grid-cols-2 gap-2">
            <!-- 1. Total Sales Card -->
            <div class="rounded-2xl border border-slate-200/90 bg-white p-3 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-[9px] font-black uppercase tracking-wider text-slate-400">Total Sales</span>
                    <div class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                        <i data-lucide="arrow-up-right" class="w-3.5 h-3.5"></i>
                    </div>
                </div>
                <p class="mt-1 text-sm sm:text-base font-black text-emerald-700 truncate" x-text="currency(metrics.sales)">₹{{ number_format($metrics['sales'], 2) }}</p>
                <p class="text-[8px] font-bold text-slate-400 mt-0.5">Gross revenue</p>
            </div>

            <!-- 2. Total Expense Card -->
            <div class="rounded-2xl border border-slate-200/90 bg-white p-3 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-[9px] font-black uppercase tracking-wider text-slate-400">Shop Expense</span>
                    <div class="flex h-6 w-6 items-center justify-center rounded-full bg-rose-50 text-rose-600">
                        <i data-lucide="arrow-down-right" class="w-3.5 h-3.5"></i>
                    </div>
                </div>
                <p class="mt-1 text-sm sm:text-base font-black text-rose-700 truncate" x-text="currency(metrics.expense)">₹{{ number_format($metrics['expense'], 2) }}</p>
                <p class="text-[8px] font-bold text-slate-400 mt-0.5">Recorded outflow</p>
            </div>

            <!-- 3. GL Bill Card with Direct Link -->
            <div class="rounded-2xl border border-amber-200/90 bg-gradient-to-br from-amber-50/60 to-orange-50/40 p-3 shadow-xs">
                <div class="flex items-center justify-between">
                    <span class="text-[9px] font-black uppercase tracking-wider text-amber-800">GL Bill</span>
                    <div class="flex h-6 w-6 items-center justify-center rounded-full bg-amber-100 text-amber-800">
                        <i data-lucide="receipt" class="w-3.5 h-3.5"></i>
                    </div>
                </div>
                <p class="mt-1 text-sm sm:text-base font-black text-amber-900 truncate" x-text="currency(metrics.gl_bills)">₹{{ number_format($metrics['gl_bills'], 2) }}</p>
                <a
                    :href="'{{ url('/admin/cashbook/reports/gl-bills') }}?shop_id={{ $currentShop->shop_id }}&timeframe=' + timeframe + '&start_date=' + startDate + '&end_date=' + endDate"
                    class="mt-1 inline-flex items-center gap-1 text-[9px] font-black text-amber-800 hover:text-amber-950 hover:underline transition"
                    title="View itemized GL invoices"
                >
                    <span>View Bills</span>
                    <i data-lucide="arrow-right" class="w-3 h-3"></i>
                </a>
            </div>

            <!-- 4. Net P/L Card -->
            <div class="rounded-2xl border p-3 shadow-xs" :class="metrics.net >= 0 ? 'border-emerald-100 bg-emerald-50/30' : 'border-rose-100 bg-rose-50/30'">
                <div class="flex items-center justify-between">
                    <span class="text-[9px] font-black uppercase tracking-wider" :class="metrics.net >= 0 ? 'text-emerald-700' : 'text-rose-700'">Net P/L</span>
                    <div class="flex h-6 w-6 items-center justify-center rounded-full" :class="metrics.net >= 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'">
                        <i :data-lucide="metrics.net >= 0 ? 'trending-up' : 'trending-down'" class="w-3.5 h-3.5"></i>
                    </div>
                </div>
                <p class="mt-1 text-sm sm:text-base font-black truncate" :class="metrics.net >= 0 ? 'text-emerald-800' : 'text-rose-800'" x-text="currency(metrics.net)">₹{{ number_format($metrics['net'], 2) }}</p>
                <p class="text-[8px] font-bold text-slate-400 mt-0.5">
                    Margin: <span class="font-black" :class="metrics.net >= 0 ? 'text-emerald-700' : 'text-rose-700'" x-text="metrics.margin_pct + '%'">{{ $metrics['margin_pct'] }}%</span>
                </p>
            </div>
        </section>

        <!-- Search Bar & Accordion Controls -->
        <section class="space-y-2">
            <div class="relative">
                <input
                    type="search"
                    x-model="searchQuery"
                    placeholder="Search category, amount, notes..."
                    class="h-9 w-full rounded-2xl border border-slate-200 bg-white pl-8 pr-3 text-xs font-semibold text-slate-900 shadow-xs outline-none focus:border-slate-400 focus:bg-white"
                >
                <i data-lucide="search" class="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-slate-400"></i>
            </div>

            <!-- Section Title + Expand/Collapse Buttons -->
            <div class="flex items-center justify-between px-1">
                <div>
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Category Summaries</h3>
                    <p class="text-[9px] font-bold text-slate-400"><span x-text="filteredCategories().length"></span> categories • Tap card to view date &amp; price</p>
                </div>
                <div class="flex items-center gap-1.5">
                    <button
                        type="button"
                        @click="expandAll()"
                        class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-[9px] font-black text-slate-600 hover:bg-slate-50 transition shadow-2xs"
                    >
                        Expand All
                    </button>
                    <button
                        type="button"
                        @click="collapseAll()"
                        class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-[9px] font-black text-slate-600 hover:bg-slate-50 transition shadow-2xs"
                    >
                        Collapse
                    </button>
                </div>
            </div>
        </section>

        <!-- Category Total Cards with Accordion Itemized Date & Price List -->
        <section class="space-y-2.5">
            <template x-for="cat in filteredCategories()" :key="cat.category">
                <div class="rounded-2xl border border-slate-200/90 bg-white shadow-xs overflow-hidden transition-all hover:border-slate-300">
                    <!-- Clickable Category Total Card Header -->
                    <div
                        @click="toggleCategory(cat.category)"
                        class="p-3 flex items-center justify-between cursor-pointer select-none bg-white hover:bg-slate-50/70 transition"
                    >
                        <div class="flex items-center gap-2.5 min-w-0">
                            <!-- Category Icon Badge -->
                            <div
                                class="h-8 w-8 rounded-xl shrink-0 flex items-center justify-center font-black"
                                :class="cat.is_gl_bill ? 'bg-amber-100 text-amber-800' : (cat.direction === 'income' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600')"
                            >
                                <i :data-lucide="cat.is_gl_bill ? 'receipt' : (cat.direction === 'income' ? 'arrow-down-left' : 'arrow-up-right')" class="w-4 h-4"></i>
                            </div>

                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <h4 class="text-xs font-black text-slate-900 truncate" x-text="cat.category"></h4>
                                    <span
                                        class="rounded-md px-1.5 py-0.2 text-[8px] font-black uppercase tracking-wider shrink-0"
                                        :class="cat.is_gl_bill ? 'bg-amber-100 text-amber-800' : (cat.direction === 'income' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700')"
                                        x-text="cat.direction"
                                    ></span>
                                </div>
                                <p class="text-[9px] font-bold text-slate-400 mt-0.5">
                                    <span x-text="cat.count"></span> <span x-text="cat.count === 1 ? 'entry' : 'entries'"></span>
                                </p>
                            </div>
                        </div>

                        <!-- Right: Total Amount + Animated Chevron -->
                        <div class="flex items-center gap-2 text-right">
                            <div>
                                <span
                                    class="text-xs sm:text-sm font-black block"
                                    :class="cat.is_gl_bill ? 'text-amber-800' : (cat.direction === 'income' ? 'text-emerald-700' : 'text-rose-700')"
                                    x-text="(cat.direction === 'income' ? '+' : '-') + currency(cat.amount)"
                                ></span>
                            </div>
                            <div class="text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': isExpanded(cat.category) }">
                                <i data-lucide="chevron-down" class="w-4 h-4"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Itemized Date & Price List (Shown when tapped/clicked) -->
                    <div
                        x-show="isExpanded(cat.category)"
                        class="border-t border-slate-100 bg-slate-50/50 p-2.5 space-y-2"
                    >
                        <template x-for="item in cat.items" :key="item.id">
                            <div class="rounded-xl border border-slate-200/80 bg-white p-2.5 shadow-2xs flex flex-col gap-2">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-[10px] font-black text-slate-900" x-text="item.formatted_date || item.business_date"></span>
                                            <span
                                                class="rounded px-1.5 py-0.2 text-[8px] font-black uppercase"
                                                :class="item.status === 'approved' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'"
                                                x-text="item.status">
                                            </span>
                                        </div>
                                        <p class="text-[10px] font-semibold text-slate-500 mt-0.5 leading-snug" x-text="item.notes || 'No description'"></p>
                                    </div>

                                    <!-- Price / Amount -->
                                    <div class="text-right shrink-0">
                                        <span
                                            class="text-xs font-black"
                                            :class="item.direction === 'income' ? 'text-emerald-700' : 'text-rose-700'"
                                            x-text="(item.direction === 'income' ? '+' : '-') + currency(item.amount)"
                                        ></span>
                                        <span class="block text-[8px] font-black uppercase text-slate-400 mt-0.5" x-text="item.funding_source || 'none'"></span>
                                    </div>
                                </div>

                                <!-- Item Actions Row -->
                                <div class="flex items-center justify-between border-t border-slate-100 pt-1.5 text-[9px] font-bold text-slate-500">
                                    <template x-if="item.is_gl_bill">
                                        <a
                                            :href="'{{ url('/admin/cashbook/reports/gl-bills') }}?shop_id={{ $currentShop->shop_id }}&timeframe=' + timeframe + '&start_date=' + startDate + '&end_date=' + endDate"
                                            class="inline-flex items-center gap-1 text-amber-800 hover:text-amber-950 font-black hover:underline"
                                        >
                                            <i data-lucide="receipt" class="w-3 h-3"></i>
                                            <span>View Bill Delivery</span>
                                        </a>
                                    </template>
                                    <template x-if="!item.is_gl_bill && item.reference_type">
                                        <span class="text-slate-400 font-semibold">Synced System Entry</span>
                                    </template>
                                    <template x-if="!item.reference_type">
                                        <div class="flex items-center gap-1.5 ml-auto">
                                            <button type="button" @click="openEditModal(item)" class="flex items-center gap-1 rounded-md border border-slate-200 bg-slate-50 px-2 py-0.5 text-slate-700 hover:bg-slate-100">
                                                <i data-lucide="edit-3" class="w-3 h-3"></i> Edit
                                            </button>
                                            <button type="button" @click="openDeleteModal(item)" class="flex items-center gap-1 rounded-md border border-rose-100 bg-rose-50 px-2 py-0.5 text-rose-700 hover:bg-rose-100">
                                                <i data-lucide="trash-2" class="w-3 h-3"></i> Delete
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            <div x-show="filteredCategories().length === 0" class="rounded-2xl border border-slate-200 bg-white p-6 text-center text-xs font-semibold text-slate-400 shadow-xs">
                No ledger transactions found for this period.
            </div>
        </section>

        <!-- Edit Amount Modal -->
        <div x-show="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs" x-cloak>
            <div class="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-4 shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                    <h3 class="text-xs font-black uppercase text-slate-900 flex items-center gap-1.5">
                        <i data-lucide="edit-3" class="w-4 h-4 text-emerald-600"></i> Edit Entry Amount
                    </h3>
                    <button type="button" @click="showEditModal = false" class="text-slate-400 hover:text-slate-600">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
                <div class="mt-4 space-y-3">
                    <div>
                        <label class="text-[9px] font-black uppercase tracking-wider text-slate-500 block mb-1">Transaction Category</label>
                        <p class="text-xs font-bold text-slate-800" x-text="editingTx ? editingTx.category_name : ''"></p>
                    </div>
                    <div>
                        <label class="text-[9px] font-black uppercase tracking-wider text-slate-500 block mb-1">New Amount (₹)</label>
                        <input type="number" step="0.01" x-model="editAmount" class="h-9 w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 text-xs font-bold text-slate-900 outline-none focus:border-emerald-500 focus:bg-white">
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2">
                    <button type="button" @click="showEditModal = false" class="flex-1 h-9 rounded-lg border border-slate-200 bg-white text-xs font-bold text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="button" @click="submitEdit()" class="flex-1 h-9 rounded-lg bg-emerald-600 text-xs font-bold text-white hover:bg-emerald-500">Save</button>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div x-show="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs" x-cloak>
            <div class="w-full max-w-sm rounded-2xl border border-rose-200 bg-white p-4 shadow-xl">
                <div class="flex items-center justify-between border-b border-rose-100 pb-2.5">
                    <h3 class="text-xs font-black uppercase text-rose-800 flex items-center gap-1.5">
                        <i data-lucide="alert-triangle" class="w-4 h-4 text-rose-600"></i> Delete Entry
                    </h3>
                    <button type="button" @click="showDeleteModal = false" class="text-slate-400 hover:text-slate-600">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
                <p class="mt-3 text-xs font-semibold text-slate-600 leading-relaxed">Are you absolutely sure you want to permanently delete this cashbook ledger entry?</p>
                <div class="mt-4 flex items-center gap-2">
                    <button type="button" @click="showDeleteModal = false" class="flex-1 h-9 rounded-lg border border-slate-200 bg-white text-xs font-bold text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="button" @click="submitDelete()" class="flex-1 h-9 rounded-lg bg-rose-600 text-xs font-bold text-white hover:bg-rose-500">Delete</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function mobileLedgerApp() {
            return {
                timeframe: '{{ $timeframe }}',
                startDate: '{{ $startDate }}',
                endDate: '{{ $endDate }}',
                searchQuery: '',
                metrics: @json($metrics),
                categories: @json($metrics['categories'] ?? []),
                expandedCategories: {},
                showEditModal: false,
                editingTx: null,
                editAmount: '',
                showDeleteModal: false,
                deletingTx: null,

                init() {
                    // Auto expand the first category if available
                    if (this.categories.length > 0) {
                        this.expandedCategories[this.categories[0].category] = true;
                    }
                    this.$nextTick(() => {
                        if (window.lucide) window.lucide.createIcons();
                    });
                },

                toggleCategory(catName) {
                    this.expandedCategories[catName] = !this.expandedCategories[catName];
                    this.$nextTick(() => {
                        if (window.lucide) window.lucide.createIcons();
                    });
                },

                isExpanded(catName) {
                    return !!this.expandedCategories[catName];
                },

                expandAll() {
                    this.filteredCategories().forEach(c => {
                        this.expandedCategories[c.category] = true;
                    });
                    this.$nextTick(() => {
                        if (window.lucide) window.lucide.createIcons();
                    });
                },

                collapseAll() {
                    this.expandedCategories = {};
                    this.$nextTick(() => {
                        if (window.lucide) window.lucide.createIcons();
                    });
                },

                filteredCategories() {
                    if (!this.searchQuery.trim()) {
                        return this.categories;
                    }
                    const q = this.searchQuery.toLowerCase();
                    return this.categories.filter(c => {
                        if (c.category.toLowerCase().includes(q)) return true;
                        if (c.amount.toString().includes(q)) return true;
                        return c.items && c.items.some(item =>
                            (item.notes && item.notes.toLowerCase().includes(q)) ||
                            item.amount.toString().includes(q) ||
                            (item.formatted_date && item.formatted_date.toLowerCase().includes(q)) ||
                            (item.business_date && item.business_date.toLowerCase().includes(q))
                        );
                    });
                },

                 setPreset(preset) {
                    this.timeframe = preset;
                    const todayStr = '{{ today()->toDateString() }}';

                    if (preset === 'today') {
                        this.startDate = todayStr;
                        this.endDate = todayStr;
                    } else if (preset === 'weekly') {
                        this.startDate = '{{ today()->startOfWeek()->toDateString() }}';
                        this.endDate = '{{ today()->endOfWeek()->toDateString() }}';
                    } else if (preset === 'monthly') {
                        this.startDate = '{{ today()->startOfMonth()->toDateString() }}';
                        this.endDate = '{{ today()->endOfMonth()->toDateString() }}';
                    } else {
                        return;
                    }

                    this.loadData();
                },

                jumpToDate(selectedDate) {
                    if (!selectedDate) return;
                    this.timeframe = 'custom';
                    this.startDate = selectedDate;
                    this.endDate = selectedDate;
                    this.loadData();
                },

                applyCustomDates() {
                    this.loadData();
                },

                loadData() {
                    const url = new URL(window.location.href);
                    url.searchParams.set('timeframe', this.timeframe);
                    url.searchParams.set('start_date', this.startDate);
                    url.searchParams.set('end_date', this.endDate);
                    window.location.href = url.toString();
                },

                openEditModal(item) {
                    this.editingTx = item;
                    this.editAmount = parseFloat(item.amount).toFixed(2);
                    this.showEditModal = true;
                    this.$nextTick(() => {
                        if (window.lucide) window.lucide.createIcons();
                    });
                },

                openDeleteModal(item) {
                    this.deletingTx = item;
                    this.showDeleteModal = true;
                    this.$nextTick(() => {
                        if (window.lucide) window.lucide.createIcons();
                    });
                },

                async submitEdit() {
                    if (!this.editingTx || !this.editAmount) return;

                    try {
                        const res = await fetch('/admin/cashbook/api/update-entry', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                transaction_id: this.editingTx.id,
                                amount: parseFloat(this.editAmount),
                            })
                        });

                        const data = await res.json();
                        if (data.success) {
                            this.showEditModal = false;
                            window.location.reload();
                        } else {
                            alert(data.message || 'Failed to update entry');
                        }
                    } catch (err) {
                        alert('Server error while updating entry');
                    }
                },

                async submitDelete() {
                    if (!this.deletingTx) return;

                    try {
                        const res = await fetch('/admin/cashbook/api/delete-entry', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                transaction_id: this.deletingTx.id,
                            })
                        });

                        const data = await res.json();
                        if (data.success) {
                            this.showDeleteModal = false;
                            window.location.reload();
                        } else {
                            alert(data.message || 'Failed to delete entry');
                        }
                    } catch (err) {
                        alert('Server error while deleting entry');
                    }
                },

                currency(value) {
                    const num = parseFloat(value || 0);
                    return '₹' + num.toLocaleString('en-IN', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                    });
                }
            };
        }
    </script>
@endpush
