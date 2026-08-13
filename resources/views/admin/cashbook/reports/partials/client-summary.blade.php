<section id="client-summary" class="white-card rounded-3xl border-l-4 border-l-slate-900 p-4 shadow-lg sm:p-6">
    <div class="mb-4 flex flex-col gap-3 border-b border-slate-100 pb-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100">
                <i data-lucide="briefcase" class="h-4.5 w-4.5 text-slate-700"></i>
            </div>
            <div>
                <h3 class="text-sm font-extrabold text-slate-900">Client Settlement Position</h3>
                <p class="text-xs text-slate-500">GL bills + company expenses deducted from shop collections</p>
            </div>
        </div>
        <span class="w-fit rounded-xl bg-slate-900 px-3 py-1.5 text-[10px] font-extrabold text-white">Clients: {{ $clients->count() }}</span>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <span class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">1. Total Shop Collections Owed</span>
            <strong id="report-rec-collections" class="font-mono text-lg font-extrabold text-slate-900">₹0.00</strong>
            <span class="block text-[10px] text-slate-500">Gross closing balance</span>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <span class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">2. Green Leaf Stock Bills</span>
            <strong id="report-gl-bills" class="font-mono text-lg font-extrabold text-amber-600">₹0.00</strong>
            <span class="block text-[10px] text-slate-500">Stock invoices issued</span>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <span class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">3. Company Paid Expenses</span>
            <strong id="report-comp-expenses" class="font-mono text-lg font-extrabold text-purple-600">₹0.00</strong>
            <span class="block text-[10px] text-slate-500">Vehicle & company pending</span>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <span class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">4. Net Settlement Position</span>
            <strong id="report-rec-net" class="font-mono text-xl font-extrabold text-slate-900">₹0.00</strong>
            <span id="report-rec-net-sub" class="block text-[10px] font-bold text-emerald-600">GL Payable to client</span>
        </div>
    </div>
</section>
