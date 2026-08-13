<section id="report-matrix" class="grid grid-cols-1 gap-6 lg:grid-cols-2">
    <div class="white-card rounded-3xl p-4 shadow-xl sm:p-6">
        <div class="mb-4 flex items-center justify-between border-b border-slate-200 pb-3">
            <h3 class="flex items-center gap-2 text-base font-bold text-slate-900">
                <i data-lucide="trophy" class="h-4 w-4 text-amber-500"></i> Shop Performance Matrix
            </h3>
            <span class="text-xs text-slate-500">Sorted by Sales</span>
        </div>
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left text-xs">
                <thead class="hidden md:table-header-group">
                    <tr class="border-b border-slate-200 bg-slate-100/80 font-bold uppercase tracking-wider text-slate-600">
                        <th class="px-3 py-2.5">Shop</th>
                        <th class="px-3 py-2.5 text-right">Sales</th>
                        <th class="px-3 py-2.5 text-right">Expense</th>
                        <th class="px-3 py-2.5 text-right">Net P/L</th>
                    </tr>
                </thead>
                <tbody id="report-performance-tbody" class="divide-y divide-slate-100 font-mono text-slate-800">
                    <tr><td colspan="4" class="py-6 text-center font-sans text-slate-400">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="white-card rounded-3xl p-4 shadow-xl sm:p-6">
        <div class="mb-4 flex items-center justify-between border-b border-slate-200 pb-3">
            <h3 class="flex items-center gap-2 text-base font-bold text-slate-900">
                <i data-lucide="pie-chart" class="h-4 w-4 text-slate-900"></i> Cash & Settlement Balances
            </h3>
            <span class="text-xs text-slate-500">Per Shop Float</span>
        </div>
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left text-xs">
                <thead class="hidden md:table-header-group">
                    <tr class="border-b border-slate-200 bg-slate-100/80 font-bold uppercase tracking-wider text-slate-600">
                        <th class="px-3 py-2.5">Shop</th>
                        <th class="px-3 py-2.5 text-right">GL Bill</th>
                        <th class="px-3 py-2.5 text-right">Payable to Co.</th>
                        <th class="px-3 py-2.5 text-right">Co. Pending</th>
                    </tr>
                </thead>
                <tbody id="report-balances-tbody" class="divide-y divide-slate-100 font-mono text-slate-800">
                    <tr><td colspan="4" class="py-6 text-center font-sans text-slate-400">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</section>
