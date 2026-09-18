@php
    $petty = $financialReport['petty'] ?? [
        'opening' => 0.0,
        'funded' => 0.0,
        'used' => 0.0,
        'current' => 0.0,
    ];
@endphp

<section class="rounded-3xl border border-slate-200/80 bg-white p-4 sm:p-6 shadow-xs space-y-5" aria-label="Shop Petty Cash Overview">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <h2 class="text-sm font-black uppercase tracking-wider text-slate-900 flex items-center gap-2">
            <span class="inline-block w-2.5 h-2.5 rounded-full bg-purple-600"></span>
            Petty
        </h2>

        @if(!empty($pettyConfig['enabled']) && !empty($pettyConfig['allow_company_to_petty']))
            <button type="button"
                    @click="showFundPettyModal = true"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-purple-600 px-3 py-1.5 text-xs font-black uppercase tracking-wider text-white shadow-xs hover:bg-purple-700 transition cursor-pointer">
                <span>+ Fund Petty</span>
            </button>
        @endif
    </div>

    <!-- 4 Petty Metrics: 1 col mobile, 2 sm, 4 lg -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4">
            <span class="text-xs font-black uppercase tracking-wider text-slate-500">Opening</span>
            <p class="mt-2 font-mono text-xl sm:text-2xl font-black text-slate-900 tabular-nums tracking-tight leading-tight break-words">
                ₹{{ number_format((float) $petty['opening'], 2) }}
            </p>
            <p class="mt-1 text-xs font-medium text-slate-500">Period Opening Balance</p>
        </div>

        <div class="rounded-2xl border border-purple-100 bg-purple-50/50 p-4">
            <span class="text-xs font-black uppercase tracking-wider text-purple-800">Company Funded</span>
            <p class="mt-2 font-mono text-xl sm:text-2xl font-black text-purple-950 tabular-nums tracking-tight leading-tight break-words">
                ₹{{ number_format((float) $petty['funded'], 2) }}
            </p>
            <p class="mt-1 text-xs font-medium text-purple-700">Company &rarr; Petty</p>
        </div>

        <div class="rounded-2xl border border-rose-100 bg-rose-50/50 p-4">
            <span class="text-xs font-black uppercase tracking-wider text-rose-800">Used</span>
            <p class="mt-2 font-mono text-xl sm:text-2xl font-black text-rose-950 tabular-nums tracking-tight leading-tight break-words">
                ₹{{ number_format((float) $petty['used'], 2) }}
            </p>
            <p class="mt-1 text-xs font-medium text-rose-700">Expenses From Petty</p>
        </div>

        <div class="rounded-2xl border border-emerald-100 bg-emerald-50/60 p-4">
            <span class="text-xs font-black uppercase tracking-wider text-emerald-800">Current Petty</span>
            <p class="mt-2 font-mono text-xl sm:text-2xl font-black text-emerald-950 tabular-nums tracking-tight leading-tight break-words">
                ₹{{ number_format((float) $petty['current'], 2) }}
            </p>
            <p class="mt-1 text-xs font-medium text-emerald-700">Closing Available Fund</p>
        </div>
    </div>
</section>
