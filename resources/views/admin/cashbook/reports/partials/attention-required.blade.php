<section id="attention-required" class="white-card rounded-3xl border border-rose-200 bg-rose-50/40 p-4 shadow-sm sm:p-6">
    <div class="mb-4 flex flex-col gap-3 border-b border-rose-100 pb-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="flex items-center gap-2 text-base font-extrabold text-slate-900">
                <i data-lucide="triangle-alert" class="h-5 w-5 text-rose-600"></i> Attention Required
            </h3>
            <p class="mt-0.5 text-xs font-medium text-slate-500">Fast operating view of unpaid and ageing receivables.</p>
        </div>
        <span id="attention-total-open" class="w-fit rounded-xl bg-white px-3 py-1.5 text-[10px] font-extrabold text-rose-700">0 open bills</span>
    </div>

    <div class="grid grid-cols-1 gap-3 min-[390px]:grid-cols-2 xl:grid-cols-4">
        <a href="#bill-details" class="rounded-2xl border border-rose-200 bg-white p-4">
            <span class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Unpaid Bills</span>
            <strong id="attention-unpaid-count" class="font-mono text-2xl font-extrabold text-rose-600">0</strong>
            <span id="attention-unpaid-value" class="block text-xs font-semibold text-rose-600">₹0.00</span>
        </a>
        <a href="#bill-details" class="rounded-2xl border border-amber-200 bg-white p-4">
            <span class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Partial Bills</span>
            <strong id="attention-partial-count" class="font-mono text-2xl font-extrabold text-amber-700">0</strong>
            <span id="attention-partial-value" class="block text-xs font-semibold text-amber-700">₹0.00</span>
        </a>
        <a href="#receivable-ageing" class="rounded-2xl border border-violet-200 bg-white p-4">
            <span class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Over 7 Days</span>
            <strong id="attention-over7-count" class="font-mono text-2xl font-extrabold text-violet-700">0</strong>
            <span id="attention-over7-value" class="block text-xs font-semibold text-violet-700">₹0.00</span>
        </a>
        <a href="#receivable-ageing" class="rounded-2xl border border-slate-200 bg-white p-4">
            <span class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Direct Open Bills</span>
            <strong id="attention-direct-open-count" class="font-mono text-2xl font-extrabold text-slate-900">0</strong>
            <span id="attention-direct-open-value" class="block text-xs font-semibold text-slate-600">₹0.00</span>
        </a>
    </div>
</section>
