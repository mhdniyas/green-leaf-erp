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
        <section class="rounded-2xl border border-slate-200 bg-white p-3.5 shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                <div class="min-w-0">
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.16em] text-slate-800 border border-slate-200">{{ $currentShop->code ?: ('SHP-' . $currentShop->shop_id) }}</span>
                    <h1 class="text-sm font-black text-slate-900 truncate mt-0.5">{{ $currentShop->name }}</h1>
                </div>
                <div class="flex items-center gap-1">
                    <a href="{{ route('admin.cashbook.reports.hub') }}" class="rounded-lg border border-slate-200 bg-slate-50 p-1.5 text-slate-600 transition hover:bg-slate-100">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    </a>
                </div>
            </div>

            <!-- Date Range Controls -->
            <div class="mt-3">
                <div class="flex flex-wrap items-center gap-1 rounded-xl border border-slate-200 bg-slate-50 p-1">
                    <button type="button" @click="setPreset('today')" class="flex-1 rounded-lg py-1 text-center text-[10px] font-black transition" :class="timeframe === 'today' ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'">Today</button>
                    <button type="button" @click="setPreset('yesterday')" class="flex-1 rounded-lg py-1 text-center text-[10px] font-black transition" :class="timeframe === 'yesterday' ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'">Yesterday</button>
                    <button type="button" @click="setPreset('weekly')" class="flex-1 rounded-lg py-1 text-center text-[10px] font-black transition" :class="timeframe === 'weekly' ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'">Week</button>
                    <button type="button" @click="setPreset('monthly')" class="flex-1 rounded-lg py-1 text-center text-[10px] font-black transition" :class="timeframe === 'monthly' ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'">Month</button>
                    <button type="button" @click="setPreset('custom')" class="flex-1 rounded-lg py-1 text-center text-[10px] font-black transition" :class="timeframe === 'custom' ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'">Custom</button>
                </div>

                <div x-show="timeframe === 'custom'" class="mt-2.5 flex items-center gap-1.5" x-cloak>
                    <input type="date" x-model="startDate" class="h-8 flex-1 rounded-lg border border-slate-200 bg-slate-50 px-2 text-xs font-bold text-slate-900">
                    <span class="text-xs font-bold text-slate-400">to</span>
                    <input type="date" x-model="endDate" class="h-8 flex-1 rounded-lg border border-slate-200 bg-slate-50 px-2 text-xs font-bold text-slate-900">
                    <button type="button" @click="applyCustomDates()" class="h-8 rounded-lg bg-emerald-600 px-3 text-xs font-bold text-white transition hover:bg-emerald-500">Apply</button>
                </div>
            </div>
        </section>

        <!-- 3 Cards in a Row Summary Section -->
        <section class="grid grid-cols-3 gap-2">
            <div class="rounded-xl border border-slate-200 bg-white p-2.5 text-center shadow-xs">
                <p class="text-[8px] font-black uppercase tracking-wider text-slate-400">Sales (In)</p>
                <p class="mt-0.5 text-xs font-black text-emerald-700" x-text="currency(metrics.sales)">₹{{ number_format($metrics['sales'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-rose-100 bg-rose-50/20 p-2.5 text-center shadow-xs">
                <p class="text-[8px] font-black uppercase tracking-wider text-rose-600">Expense (Out)</p>
                <p class="mt-0.5 text-xs font-black text-rose-700" x-text="currency(metrics.expense)">₹{{ number_format($metrics['expense'], 2) }}</p>
            </div>
            <div class="rounded-xl border p-2.5 text-center shadow-xs" :class="metrics.net >= 0 ? 'bg-emerald-50/30 border-emerald-100' : 'bg-rose-50/30 border-rose-100'">
                <p class="text-[8px] font-black uppercase tracking-wider" :class="metrics.net >= 0 ? 'text-emerald-600' : 'text-rose-600'">Net P/L</p>
                <p class="mt-0.5 text-xs font-black" :class="metrics.net >= 0 ? 'text-emerald-800' : 'text-rose-800'" x-text="currency(metrics.net)">₹{{ number_format($metrics['net'], 2) }}</p>
            </div>
        </section>

        <!-- Search Bar & Filters -->
        <section class="flex gap-2">
            <div class="relative flex-1">
                <input
                    type="search"
                    x-model="searchQuery"
                    placeholder="Search by category, amount, notes..."
                    class="h-9 w-full rounded-xl border border-slate-200 bg-white pl-8 pr-3 text-xs font-semibold text-slate-900 outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                >
                <i data-lucide="search" class="absolute left-2.5 top-2.5 w-4 h-4 text-slate-400"></i>
            </div>
        </section>

        <!-- Mobile App Styled Transactions List -->
        <section class="space-y-2">
            <template x-for="tx in filteredTransactions()" :key="tx.id">
                <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-xs flex flex-col justify-between gap-2.5">
                    <div class="flex items-start justify-between gap-2">
                        <!-- Left: Status Icon and Details -->
                        <div class="flex gap-2.5">
                            <div class="h-8 w-8 rounded-full shrink-0 flex items-center justify-center"
                                 :class="isIncome(tx) ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'">
                                <i :data-lucide="isIncome(tx) ? 'arrow-down-left' : 'arrow-up-right'" class="w-4 h-4"></i>
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-xs font-black text-slate-900" x-text="tx.category_name"></h3>
                                <p class="text-[10px] font-bold text-slate-400" x-text="formatDate(tx.business_date)"></p>
                                <p class="text-[10px] font-semibold text-slate-500 mt-0.5 leading-tight" x-text="tx.notes || 'No description'"></p>
                            </div>
                        </div>

                        <!-- Right: Amount and Badges -->
                        <div class="text-right">
                            <span class="text-sm font-black" :class="isIncome(tx) ? 'text-emerald-700' : 'text-rose-700'"
                                  x-text="(isIncome(tx) ? '+' : '-') + currency(tx.amount)">
                            </span>
                            <span class="block text-[8px] font-black uppercase text-slate-400 mt-0.5" x-text="tx.funding_source || 'none'"></span>
                        </div>
                    </div>

                    <!-- Row Controls: Sync state, Edit/Delete if Manual Entry -->
                    <div class="flex items-center justify-between border-t border-slate-100 pt-2 text-[10px] font-bold text-slate-500">
                        <div class="flex items-center gap-1">
                            <span class="rounded-md px-1.5 py-0.5 text-[8px] font-black uppercase tracking-wider"
                                  :class="tx.status === 'approved' ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-amber-50 text-amber-700 border border-amber-100'"
                                  x-text="tx.status">
                            </span>
                            <template x-if="tx.reference_type">
                                <span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[8px] font-black uppercase text-slate-600">Synced Invoice</span>
                            </template>
                        </div>

                        <!-- Edit & Delete Buttons: Only if NOT reference_type (meaning added as entry only) -->
                        <template x-if="!tx.reference_type">
                            <div class="flex items-center gap-1.5">
                                <button type="button" @click="openEditModal(tx)" class="flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-slate-700 hover:bg-slate-100">
                                    <i data-lucide="edit-3" class="w-3 h-3"></i> Edit
                                </button>
                                <button type="button" @click="openDeleteModal(tx)" class="flex items-center gap-1 rounded-lg border border-rose-100 bg-rose-50 px-2 py-1 text-rose-700 hover:bg-rose-100">
                                    <i data-lucide="trash-2" class="w-3 h-3"></i> Delete
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            <div x-show="filteredTransactions().length === 0" class="rounded-xl border border-slate-200 bg-white p-6 text-center text-xs font-semibold text-slate-400">
                No ledger transactions found.
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
                transactions: [],
                showEditModal: false,
                editingTx: null,
                editAmount: '',
                showDeleteModal: false,
                deletingTx: null,

                init() {
                    this.loadTransactionsList();
                },

                isIncome(tx) {
                    return tx.direction === 'income' || (tx.entry_type && tx.entry_type.category === 'income');
                },

                loadTransactionsList() {
                    // Populate from metrics data
                    if (this.metrics && this.metrics.transactions) {
                        this.transactions = this.metrics.transactions.map(t => ({
                            id: t.id,
                            amount: t.amount,
                            direction: t.direction,
                            business_date: t.business_date,
                            category_name: (t.entry_type && t.entry_type.name) ? t.entry_type.name : t.entry_type_code,
                            notes: t.notes,
                            status: t.status,
                            funding_source: t.funding_source,
                            reference_type: t.reference_type,
                        }));
                    }
                    this.$nextTick(() => {
                        if (window.lucide) window.lucide.createIcons();
                    });
                },

                filteredTransactions() {
                    if (!this.searchQuery.trim()) {
                        return this.transactions;
                    }
                    const q = this.searchQuery.toLowerCase();
                    return this.transactions.filter(t =>
                        t.category_name.toLowerCase().includes(q) ||
                        (t.notes && t.notes.toLowerCase().includes(q)) ||
                        t.amount.toString().includes(q)
                    );
                },

                setPreset(preset) {
                    this.timeframe = preset;
                    const todayStr = '{{ today()->toDateString() }}';
                    const yesterdayStr = '{{ today()->subDay()->toDateString() }}';

                    if (preset === 'today') {
                        this.startDate = todayStr;
                        this.endDate = todayStr;
                    } else if (preset === 'yesterday') {
                        this.startDate = yesterdayStr;
                        this.endDate = yesterdayStr;
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

                applyCustomDates() {
                    this.loadData();
                },

                async loadData() {
                    try {
                        const params = new URLSearchParams({
                            timeframe: this.timeframe,
                            start_date: this.startDate,
                            end_date: this.endDate,
                            shop_id: '{{ $currentShop->shop_id }}'
                        });

                        // Reuse apiHubData to query specific shop
                        const response = await fetch(`{{ route('admin.cashbook.reports.api.hub') }}?${params.toString()}`);
                        const payload = await response.json();

                        if (payload.success) {
                            // Find current shop metrics
                            const current = payload.shopMetrics.find(s => s.shop_id === {{ $currentShop->shop_id }});
                            if (current) {
                                this.metrics.sales = current.sales;
                                this.metrics.expense = current.expense;
                                this.metrics.net = current.net;
                            }

                            // Fetch full detail for transactions list
                            const detailRes = await fetch(`{{ url('/admin/cashbook/reports/shop') }}/{{ $currentShop->slug ?: $currentShop->shop_id }}?timeframe=${this.timeframe}&start_date=${this.startDate}&end_date=${this.endDate}`);
                            const html = await detailRes.text();

                            // Temporarily fetch and parse metrics
                            const doc = new DOMParser().parseFromString(html, 'text/html');
                            // Let's reload page window for clean sync
                            const url = new URL(window.location.href);
                            url.searchParams.set('timeframe', this.timeframe);
                            url.searchParams.set('start_date', this.startDate);
                            url.searchParams.set('end_date', this.endDate);
                            window.location.href = url.toString();
                        }
                    } catch (err) {
                        console.error('Failed to load mobile data:', err);
                    }
                },

                openEditModal(tx) {
                    this.editingTx = tx;
                    this.editAmount = parseFloat(tx.amount).toFixed(2);
                    this.showEditModal = true;
                },

                openDeleteModal(tx) {
                    this.deletingTx = tx;
                    this.showDeleteModal = true;
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
                },

                formatDate(dateStr) {
                    if (!dateStr) return '';
                    try {
                        const date = new Date(dateStr);
                        return date.toLocaleDateString('en-IN', { day: '2-digit', month: 'short' });
                    } catch (e) {
                        return dateStr;
                    }
                }
            };
        }
    </script>
@endpush
