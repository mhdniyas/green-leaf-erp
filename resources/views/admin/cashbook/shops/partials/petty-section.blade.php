@php
    $pettyPeriodLabel = $isDayDetail
        ? \Illuminate\Support\Carbon::parse($businessDate)->format('d M Y')
        : \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->format('F Y');
    $isPettyEnabled = !empty($pettyConfig['enabled']);
    $canFundPetty = $isPettyEnabled && !empty($pettyConfig['allow_company_to_petty']);
@endphp

<div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-6"
     x-data="{ expanded: false }">
    <!-- Header & Action Row -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-4">
        <div class="flex items-center gap-3">
            <div class="p-2.5 rounded-2xl bg-emerald-50 text-emerald-700 border border-emerald-100">
                <i data-lucide="coins" class="w-5 h-5"></i>
            </div>
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-sm font-black text-slate-900 uppercase tracking-wide">Shop Petty Cash &amp; Floating Fund</h2>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 font-mono">
                        {{ $pettyPeriodLabel }}
                    </span>
                    @if($isPettyEnabled)
                        <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                            Petty Enabled
                        </span>
                    @else
                        <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 border border-slate-200">
                            Petty Disabled
                        </span>
                    @endif
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Track company advances into shop petty float, cash spending, and live as-of balance.</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            @if($canFundPetty)
                <button type="button"
                        @click="showFundPettyModal = true"
                        class="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white text-xs font-black rounded-2xl shadow-sm hover:shadow transition-all">
                    <i data-lucide="plus-circle" class="w-4 h-4"></i>
                    <span>Add Petty</span>
                </button>
            @else
                <div class="inline-flex items-center gap-1.5 px-3 py-2 bg-slate-100 text-slate-400 text-xs font-bold rounded-2xl cursor-not-allowed"
                     title="Enable 'Allow Company to Petty funding' in Shop Payment Settings to use this action">
                    <i data-lucide="lock" class="w-3.5 h-3.5"></i>
                    <span>Add Petty (Disabled)</span>
                </div>
            @endif

            <button type="button"
                    @click="expanded = !expanded"
                    class="p-2 rounded-xl text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition cursor-pointer"
                    title="Toggle Petty Section">
                <i data-lucide="chevron-down" class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': expanded }"></i>
            </button>
        </div>
    </div>

    <div x-show="expanded" class="space-y-6">
        <!-- 3 Metric Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- 1. Current Petty Balance (As-of Period End) -->
            <div class="p-5 rounded-2xl bg-gradient-to-br from-slate-900 to-slate-800 text-white shadow-sm flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Current Petty Balance</span>
                    <span class="text-[9px] font-black px-2 py-0.5 rounded-full bg-slate-700 text-slate-300 font-mono">
                        As of {{ \Illuminate\Support\Carbon::parse($periodEnd)->format('d M') }}
                    </span>
                </div>
                <div class="mt-3">
                    <p class="text-2xl font-black font-mono text-emerald-400">
                        ₹{{ number_format($currentPettyBalance, 2) }}
                    </p>
                    <p class="text-[11px] text-slate-400 mt-1">Available cash on hand in shop petty float</p>
                </div>
            </div>

            <!-- 2. Company Funded in Period -->
            <div class="p-5 rounded-2xl border border-emerald-200 bg-emerald-50/50 shadow-sm flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-black uppercase tracking-widest text-emerald-800 flex items-center gap-1">
                        <i data-lucide="arrow-down-left" class="w-3.5 h-3.5 text-emerald-600"></i>
                        Company Funded
                    </span>
                    <span class="text-[9px] font-bold px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800">
                        + Inflow
                    </span>
                </div>
                <div class="mt-3">
                    <p class="text-2xl font-black font-mono text-emerald-800">
                        +₹{{ number_format($companyFundedPeriod, 2) }}
                    </p>
                    <p class="text-[11px] text-emerald-700/80 mt-1">Transferred from company accounts to float</p>
                </div>
            </div>

            <!-- 3. Petty Used / Expenses in Period -->
            <div class="p-5 rounded-2xl border border-rose-200 bg-rose-50/50 shadow-sm flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-black uppercase tracking-widest text-rose-800 flex items-center gap-1">
                        <i data-lucide="arrow-up-right" class="w-3.5 h-3.5 text-rose-600"></i>
                        Petty Used / Spent
                    </span>
                    <span class="text-[9px] font-bold px-2 py-0.5 rounded-md bg-rose-100 text-rose-800">
                        − Outflow
                    </span>
                </div>
                <div class="mt-3">
                    <p class="text-2xl font-black font-mono text-rose-800">
                        -₹{{ number_format($pettyUsedPeriod, 2) }}
                    </p>
                    <p class="text-[11px] text-rose-700/80 mt-1">Cash expenses funded directly from petty float</p>
                </div>
            </div>
        </div>

        <!-- Petty History Table -->
        <div class="space-y-3 pt-2">
            <div class="flex items-center justify-between">
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                    <i data-lucide="history" class="w-3.5 h-3.5 text-slate-400"></i>
                    <span>Petty Cash History</span>
                </h3>
                <div class="flex items-center gap-2">
                    <span class="text-[11px] text-slate-400 font-mono font-bold">
                        Latest {{ $pettyHistory->count() }} records
                    </span>
                    <a href="{{ route('admin.cashbook.shop.history.petty', $currentShop->slug ?: $currentShop->shop_id) }}"
                       class="inline-flex items-center gap-1 text-xs font-black text-emerald-700 hover:text-emerald-800 hover:underline">
                        <span>View More</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>
            </div>

            @include('admin.cashbook.shops.partials.petty-table', ['pettyTransactions' => $pettyHistory, 'isFull' => false])
        </div>
    </div>
</div>
