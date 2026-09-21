<div x-data="{
    open: false,
    loading: false,
    metric: '',
    metricLabel: '',
    total: 0,
    rows: [],
    date: null,
    purchaserId: null,
    openDrilldown(metricName, metricTitle, optDate = null, optPurchaserId = null) {
        this.open = true;
        this.loading = true;
        this.metric = metricName;
        this.metricLabel = metricTitle || metricName;
        this.date = optDate;
        this.purchaserId = optPurchaserId;
        this.rows = [];
        this.total = 0;

        let params = new URLSearchParams(window.location.search);
        params.set('metric', metricName);
        if (optDate) params.set('date', optDate);
        if (optPurchaserId) params.set('purchaser_id', optPurchaserId);

        fetch('{{ route('admin.cashbook.monthly-report.drilldown') }}?' + params.toString(), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            this.metricLabel = data.metric_label || metricTitle;
            this.total = data.total || 0;
            this.rows = data.rows || [];
            this.loading = false;
            $nextTick(() => { if (window.lucide) lucide.createIcons(); });
        })
        .catch(err => {
            console.error('Drilldown fetch error:', err);
            this.loading = false;
        });
    }
}"
x-on:open-drilldown.window="openDrilldown($event.detail.metric, $event.detail.title, $event.detail.date, $event.detail.purchaserId)"
class="relative z-50">

    <!-- BACKDROP -->
    <div x-show="open" x-cloak
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs"></div>

    <!-- SLIDE-OVER DRAWER -->
    <div x-show="open" x-cloak
         x-transition:enter="transform transition ease-in-out duration-300"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transform transition ease-in-out duration-200"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full"
         class="fixed inset-y-0 right-0 flex max-w-full pl-10">

        <div class="w-screen max-w-2xl bg-white shadow-2xl flex flex-col">
            <!-- DRAWER HEADER -->
            <div class="border-b border-slate-200 bg-slate-50/80 px-6 py-4 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-extrabold text-slate-900" x-text="metricLabel"></h3>
                    <p class="text-xs font-semibold text-slate-500 mt-0.5">
                        <span x-show="date" x-text="'Date: ' + date + ' · '"></span>
                        Authorized Financial Audit Breakdown
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="text-right">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total</span>
                        <div class="font-mono text-sm font-black text-slate-900" x-text="'₹' + Number(total).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></div>
                    </div>
                    <button type="button" @click="open = false" class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-200/60 hover:text-slate-700 transition">
                        <i data-lucide="x" class="h-5 w-5"></i>
                    </button>
                </div>
            </div>

            <!-- DRAWER BODY -->
            <div class="flex-1 overflow-y-auto p-6">
                <!-- LOADING STATE -->
                <div x-show="loading" class="flex flex-col items-center justify-center py-20 text-slate-400">
                    <i data-lucide="loader-2" class="h-8 w-8 animate-spin text-indigo-600"></i>
                    <span class="mt-2 text-xs font-semibold">Resolving financial source records...</span>
                </div>

                <!-- EMPTY STATE -->
                <div x-show="!loading && rows.length === 0" class="flex flex-col items-center justify-center py-20 text-slate-400">
                    <i data-lucide="inbox" class="h-8 w-8 text-slate-300"></i>
                    <span class="mt-2 text-xs font-semibold">No detailed source transactions for this selection.</span>
                </div>

                <!-- ROWS LIST -->
                <div x-show="!loading && rows.length > 0" class="space-y-2.5">
                    <template x-for="(r, idx) in rows" :key="idx">
                        <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-3.5 transition hover:bg-white hover:border-indigo-100 hover:shadow-xs">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="rounded-md bg-indigo-50 px-2 py-0.5 text-[10px] font-black text-indigo-700" x-text="r.source_type || r.type || 'Transaction'"></span>
                                        <span class="font-mono text-xs font-bold text-slate-800" x-text="r.reference || ('#' + (r.id || idx))"></span>
                                        <span class="text-[11px] font-medium text-slate-400" x-text="r.business_date || r.date"></span>
                                    </div>
                                    <div class="mt-1 text-xs font-extrabold text-slate-900" x-text="r.entity_name || r.shop_name || r.client_name || r.purchaser_name"></div>
                                    <div class="mt-0.5 text-xs text-slate-500" x-text="r.description || r.notes || r.original_category"></div>
                                </div>
                                <div class="text-right whitespace-nowrap">
                                    <div class="font-mono text-xs font-black text-slate-900" x-text="'₹' + Number(r.amount || r.out_amount || r.in_amount || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></div>
                                    <span class="text-[10px] font-semibold text-slate-400" x-text="r.funding_source ? 'Source: ' + r.funding_source : (r.bucket_label || '')"></span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- DRAWER FOOTER -->
            <div class="border-t border-slate-100 bg-slate-50 px-6 py-3 flex items-center justify-between text-xs text-slate-500">
                <span x-text="rows.length + ' item(s) reconciled'"></span>
                <button type="button" @click="open = false" class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 font-bold text-slate-700 hover:bg-slate-100">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>
