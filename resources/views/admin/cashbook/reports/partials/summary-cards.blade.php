<section id="executive-summary" class="space-y-4">
    <div class="white-card rounded-3xl border-l-4 border-l-emerald-600 p-4 shadow-lg sm:p-6">
        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-600">
                    <i data-lucide="leaf" class="h-5 w-5 text-white"></i>
                </div>
                <div>
                    <h2 class="text-base font-extrabold text-slate-900">Green Leaf — Admin Receivables</h2>
                    <p class="text-xs font-medium text-slate-500">Stock billed vs. received for {{ $reportRangeLabel }}</p>
                </div>
            </div>
            <span class="w-fit rounded-xl border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[10px] font-extrabold text-emerald-700">Green Leaf</span>
        </div>

        <div class="grid grid-cols-1 gap-3 min-[390px]:grid-cols-2 lg:grid-cols-5">
            <a href="#report-matrix" class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                <span class="mb-1 block text-[10px] font-extrabold uppercase tracking-wider text-amber-600">GL Bills Issued</span>
                <div id="gl-total-bills" class="font-mono text-xl font-extrabold text-amber-700">₹0.00</div>
                <span class="text-[10px] font-medium text-amber-600">Stock invoices</span>
            </a>
            <a href="#report-matrix" class="rounded-2xl border border-purple-200 bg-purple-50 p-4">
                <span class="mb-1 block text-[10px] font-extrabold uppercase tracking-wider text-purple-600">Company Advances</span>
                <div id="gl-company-pending" class="font-mono text-xl font-extrabold text-purple-700">₹0.00</div>
                <span class="text-[10px] font-medium text-purple-600">Vehicle & petty paid</span>
            </a>
            <a href="#report-matrix" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                <span class="mb-1 block text-[10px] font-extrabold uppercase tracking-wider text-emerald-600">Received</span>
                <div id="gl-received" class="font-mono text-xl font-extrabold text-emerald-700">₹0.00</div>
                <span class="text-[10px] font-medium text-emerald-600">Payments from shops</span>
            </a>
            <a href="#client-summary" class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
                <span class="mb-1 block text-[10px] font-extrabold uppercase tracking-wider text-slate-300">Net Receivable</span>
                <div id="gl-net-receivable" class="font-mono text-xl font-extrabold text-white">₹0.00</div>
                <span class="text-[10px] font-medium text-slate-400">Still owed to GL</span>
            </a>
            <a href="#report-matrix" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 min-[390px]:col-span-2 lg:col-span-1">
                <span class="mb-1 block text-[10px] font-extrabold uppercase tracking-wider text-slate-500">Total Shop Sales</span>
                <div id="gl-total-sales" class="font-mono text-xl font-extrabold text-slate-900">₹0.00</div>
                <span class="text-[10px] font-medium text-slate-500">All shops combined</span>
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 min-[390px]:grid-cols-2 xl:grid-cols-4">
        <a href="#report-matrix" class="white-card rounded-3xl p-5 shadow-sm">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Aggregate Sales</span>
            <div id="report-total-sales" class="font-mono text-2xl font-extrabold text-slate-900">₹0.00</div>
            <span class="block text-xs font-semibold text-emerald-600">Across {{ $shops->count() }} Shops</span>
        </a>
        <a href="#report-matrix" class="white-card rounded-3xl p-5 shadow-sm">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Aggregate Expense</span>
            <div id="report-total-expense" class="font-mono text-2xl font-extrabold text-rose-600">₹0.00</div>
            <span class="block text-xs font-semibold text-rose-600">Total chargeable P/L expense</span>
        </a>
        <a href="#report-matrix" class="white-card rounded-3xl p-5 shadow-sm">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Aggregate Net P/L</span>
            <div id="report-net-pl" class="font-mono text-2xl font-extrabold text-slate-900">₹0.00</div>
            <span class="block text-xs font-medium text-slate-500">Net profit / loss</span>
        </a>
        <a href="#client-summary" class="white-card rounded-3xl p-5 shadow-sm">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Total Company Payables</span>
            <div id="report-shop-position" class="font-mono text-2xl font-extrabold text-amber-600">₹0.00</div>
            <span class="block text-xs font-semibold text-amber-600">Total collections owed</span>
        </a>
    </div>
</section>
