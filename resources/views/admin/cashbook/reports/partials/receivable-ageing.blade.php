<section id="receivable-ageing" class="white-card rounded-3xl border border-slate-200 p-4 shadow-xl sm:p-6">
    <div class="mb-4 flex flex-col gap-3 border-b border-slate-200 pb-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="flex items-center gap-2 text-base font-extrabold text-slate-900">
                <i data-lucide="hourglass" class="h-5 w-5 text-violet-600"></i> Receivable Ageing
            </h3>
            <p class="mt-0.5 text-xs font-medium text-slate-500">Open bill balances bucketed by age from invoice date.</p>
        </div>
        <span id="ageing-total-balance" class="w-fit rounded-xl bg-violet-50 px-3 py-1.5 text-[10px] font-extrabold text-violet-700">₹0.00 open balance</span>
    </div>

    <div class="grid grid-cols-1 gap-3 min-[390px]:grid-cols-2 xl:grid-cols-5">
        @foreach([
            ['key' => '0_7', 'label' => '0–7 Days'],
            ['key' => '8_14', 'label' => '8–14 Days'],
            ['key' => '15_30', 'label' => '15–30 Days'],
            ['key' => '31_60', 'label' => '31–60 Days'],
            ['key' => 'above_60', 'label' => '60+ Days'],
        ] as $bucket)
            <a href="#bill-details" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <span class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">{{ $bucket['label'] }}</span>
                <strong id="ageing-{{ $bucket['key'] }}-value" class="mt-2 block font-mono text-xl font-extrabold text-slate-900">₹0.00</strong>
                <span id="ageing-{{ $bucket['key'] }}-count" class="block text-xs font-semibold text-slate-500">0 bills</span>
            </a>
        @endforeach
    </div>
</section>
