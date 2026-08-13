<section id="bill-details" class="white-card rounded-3xl border border-slate-200 p-4 shadow-xl sm:p-6">
    <div class="mb-4 flex flex-col gap-3 border-b border-slate-200 pb-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="flex items-center gap-2 text-base font-extrabold text-slate-900">
                <i data-lucide="receipt" class="h-5 w-5 text-emerald-600"></i> Bill Details
            </h3>
            <p class="mt-0.5 text-xs font-medium text-slate-500">Invoice-level bill visibility for clients and direct shops.</p>
        </div>
        <span id="bill-count" class="w-fit rounded-xl bg-emerald-50 px-3 py-1.5 text-[10px] font-extrabold text-emerald-700">0 bills</span>
    </div>

    <div class="mb-4 grid grid-cols-1 gap-3 min-[390px]:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <span class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Billed</span>
            <strong id="bill-total-billed" class="font-mono text-xl font-extrabold text-slate-900">₹0.00</strong>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <span class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Paid</span>
            <strong id="bill-total-paid" class="font-mono text-xl font-extrabold text-emerald-600">₹0.00</strong>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <span class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Balance</span>
            <strong id="bill-total-balance" class="font-mono text-xl font-extrabold text-amber-600">₹0.00</strong>
        </div>
    </div>

    <div class="hidden overflow-x-auto lg:block">
        <table class="w-full text-left text-xs">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-100/80 font-bold uppercase tracking-wider text-slate-600">
                    <th class="px-3 py-2.5">Date</th>
                    <th class="px-3 py-2.5">Invoice</th>
                    <th class="px-3 py-2.5">Scope</th>
                    <th class="px-3 py-2.5">Shop</th>
                    <th class="px-3 py-2.5 text-right">Bill</th>
                    <th class="px-3 py-2.5 text-right">Paid</th>
                    <th class="px-3 py-2.5 text-right">Balance</th>
                    <th class="px-3 py-2.5">Status</th>
                </tr>
            </thead>
            <tbody id="bill-details-tbody" class="divide-y divide-slate-100 font-mono text-slate-800">
                <tr><td colspan="8" class="py-6 text-center font-sans text-slate-400">Loading bills...</td></tr>
            </tbody>
        </table>
    </div>

    <div id="bill-details-cards" class="space-y-3 lg:hidden">
        <div class="rounded-2xl border border-dashed border-slate-200 p-5 text-center text-sm font-bold text-slate-400">Loading bills...</div>
    </div>
</section>
