@php
    $pettyPeriodLabel = $isDayDetail
        ? \Illuminate\Support\Carbon::parse($businessDate)->format('d M Y')
        : \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->format('F Y');
    $isPettyEnabled = !empty($pettyConfig['enabled']);
    $canFundPetty = $isPettyEnabled && !empty($pettyConfig['allow_company_to_petty']);
@endphp

<div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-6">
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
        </div>
    </div>

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
            <span class="text-[11px] text-slate-400 font-mono font-bold">
                Showing latest {{ $pettyHistory->count() }} records
            </span>
        </div>

        <div class="border border-slate-200 rounded-2xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-[10px] tracking-wider">
                            <th class="py-3 px-4">Date</th>
                            <th class="py-3 px-4">Type / Description</th>
                            <th class="py-3 px-4">Source / Account</th>
                            <th class="py-3 px-4 text-right">Petty Movement</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4">Recorded By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($pettyHistory as $tx)
                            @php
                                $delta = (float) $tx->petty_delta;
                                $isPositive = $delta > 0;
                                $isNegative = $delta < 0;
                                $isVoid = in_array($tx->status, ['void', 'voided', \App\Enums\Cashbook\TransactionStatus::Void->value], true);
                                $isReversed = in_array($tx->status, ['reversed', \App\Enums\Cashbook\TransactionStatus::Reversed->value], true);
                            @endphp
                            <tr class="hover:bg-slate-50/80 transition-colors {{ ($isVoid || $isReversed) ? 'opacity-50 bg-slate-50/50 line-through' : '' }}">
                                <!-- Date -->
                                <td class="py-3 px-4 font-mono font-medium text-slate-700 whitespace-nowrap">
                                    {{ \Illuminate\Support\Carbon::parse($tx->business_date)->format('d M Y') }}
                                </td>

                                <!-- Description -->
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-800">
                                        {{ $tx->entryType?->name ?: ($tx->entry_type_code === 'company_to_petty' ? 'Company to Petty Funding' : $tx->entry_type_code) }}
                                    </div>
                                    @if($tx->notes)
                                        <div class="text-[11px] text-slate-500 truncate max-w-xs">{{ $tx->notes }}</div>
                                    @endif
                                </td>

                                <!-- Source / Account -->
                                <td class="py-3 px-4 whitespace-nowrap">
                                    @if($tx->companyAccount)
                                        <span class="inline-flex items-center gap-1 text-[11px] font-bold text-slate-700 bg-slate-100 px-2 py-0.5 rounded-md">
                                            <i data-lucide="building" class="w-3 h-3 text-slate-400"></i>
                                            {{ $tx->companyAccount->name }}
                                        </span>
                                    @elseif($tx->funding_source === 'petty')
                                        <span class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md">
                                            <i data-lucide="coins" class="w-3 h-3 text-emerald-500"></i>
                                            Shop Petty Float
                                        </span>
                                    @else
                                        <span class="text-[11px] text-slate-400 font-mono">{{ $tx->funding_source ?: '—' }}</span>
                                    @endif
                                </td>

                                <!-- Petty Movement Delta -->
                                <td class="py-3 px-4 text-right font-mono font-black whitespace-nowrap">
                                    @if($isPositive)
                                        <span class="text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md">
                                            +₹{{ number_format($delta, 2) }}
                                        </span>
                                    @elseif($isNegative)
                                        <span class="text-rose-700 bg-rose-50 px-2 py-0.5 rounded-md">
                                            -₹{{ number_format(abs($delta), 2) }}
                                        </span>
                                    @else
                                        <span class="text-slate-400">₹0.00</span>
                                    @endif
                                </td>

                                <!-- Status -->
                                <td class="py-3 px-4 text-center whitespace-nowrap">
                                    @if($isVoid)
                                        <span class="text-[10px] font-black px-2 py-0.5 rounded bg-rose-100 text-rose-800">VOID</span>
                                    @elseif($isReversed)
                                        <span class="text-[10px] font-black px-2 py-0.5 rounded bg-amber-100 text-amber-800">REVERSED</span>
                                    @elseif(in_array($tx->status, ['approved', \App\Enums\Cashbook\TransactionStatus::Approved->value], true))
                                        <span class="text-[10px] font-black px-2 py-0.5 rounded bg-emerald-100 text-emerald-800">APPROVED</span>
                                    @else
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-700 uppercase">{{ $tx->status ?: 'POSTED' }}</span>
                                    @endif
                                </td>

                                <!-- Recorded By -->
                                <td class="py-3 px-4 text-slate-500 text-[11px] whitespace-nowrap">
                                    {{ $tx->enteredBy?->name ?: 'System' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-400 font-medium">
                                    <i data-lucide="inbox" class="w-6 h-6 mx-auto mb-1 text-slate-300"></i>
                                    No petty cash transactions recorded for this period.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
