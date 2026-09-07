@php
    $payableSettlement = collect($configuredSettlements)->firstWhere('is_company_payable', true)
        ?? collect($configuredSettlements)->firstWhere('relation_type', 'default_company_payable')
        ?? collect($configuredSettlements)->first();

    $otherCustomSettlements = collect($configuredSettlements)->reject(function ($s) use ($payableSettlement) {
        $type = $s['relation_type'] ?? '';
        return in_array($type, ['default_income', 'default_expense', 'default_balance'], true)
            || ($payableSettlement && ($s['relation_id'] ?? null) === ($payableSettlement['relation_id'] ?? null));
    })->values();

    $items = $payableSettlement['items'] ?? [];
    $payments = $payableSettlement['payments'] ?? [];
    $formulaNet = (float) ($payableSettlement['netSettlement'] ?? 0);
    $paymentsTotal = (float) ($payableSettlement['verified_payments_total'] ?? 0);
    $netPayable = (float) ($payableSettlement['remaining_company_payable'] ?? ($formulaNet - $paymentsTotal));
@endphp

<section class="space-y-4" aria-label="Company Payable and Settlement Summary">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-base font-black text-slate-950 uppercase tracking-wide flex items-center gap-2">
                <span class="inline-block w-2.5 h-2.5 rounded-full bg-indigo-600"></span>
                Company Payable & Settlement
            </h2>
            <p class="text-xs text-slate-500 font-medium">Dynamic category formula & verified payments received for {{ $isDayDetail ? 'selected day' : 'selected month' }}</p>
        </div>
        <a href="{{ route('admin.cashbook.settings.shop.settlements.index', $currentShop->slug ?: $currentShop->shop_id) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-indigo-200 bg-indigo-50/50 hover:bg-indigo-100 text-xs font-extrabold text-indigo-700 transition">
            <span>Configure Settlements</span>
            <span>&rarr;</span>
        </a>
    </div>

    @if($payableSettlement)
        <div class="rounded-3xl border border-indigo-100 bg-linear-to-br from-white via-indigo-50/30 to-white p-6 shadow-sm space-y-6">
            <!-- 3 Main Pillars: Formula Gross, Payments Received, Net Remaining Payable -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs">
                    <span class="text-[11px] font-black uppercase tracking-wider text-slate-500">1. Settlement Output</span>
                    <p class="mt-2 font-mono text-2xl font-black {{ $formulaNet < 0 ? 'text-rose-700' : 'text-slate-900' }}">
                        ₹{{ number_format($formulaNet, 2) }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500 font-medium">Net calculated from {{ count($items) }} {{ Str::plural('category', count($items)) }}</p>
                </div>

                <div class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-5 shadow-xs">
                    <span class="text-[11px] font-black uppercase tracking-wider text-emerald-800">2. Verified Payments Received</span>
                    <p class="mt-2 font-mono text-2xl font-black text-emerald-800">
                        − ₹{{ number_format($paymentsTotal, 2) }}
                    </p>
                    <p class="mt-1 text-xs text-emerald-700 font-medium">{{ count($payments) }} verified {{ Str::plural('payment', count($payments)) }} received</p>
                </div>

                <div class="rounded-2xl border {{ $netPayable > 0 ? 'border-indigo-300 bg-indigo-50/80' : ($netPayable < 0 ? 'border-amber-300 bg-amber-50/80' : 'border-emerald-300 bg-emerald-50/80') }} p-5 shadow-xs">
                    <span class="text-[11px] font-black uppercase tracking-wider {{ $netPayable > 0 ? 'text-indigo-900' : 'text-slate-700' }}">3. Remaining Company Payable</span>
                    <p class="mt-2 font-mono text-2xl font-black {{ $netPayable > 0 ? 'text-indigo-950' : ($netPayable < 0 ? 'text-amber-900' : 'text-emerald-900') }}">
                        ₹{{ number_format($netPayable, 2) }}
                    </p>
                    <p class="mt-1 text-xs font-semibold {{ $netPayable > 0 ? 'text-indigo-700' : ($netPayable < 0 ? 'text-amber-800' : 'text-emerald-700') }}">
                        {{ $netPayable > 0 ? 'Due to company' : ($netPayable < 0 ? 'Credit balance / Overpaid' : 'Fully settled') }}
                    </p>
                </div>
            </div>

            <!-- Supporting Category Metrics Breakdown -->
            @if(!empty($items))
                <div class="rounded-2xl border border-slate-200/80 bg-white p-5 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-black uppercase tracking-wider text-slate-600">Settlement Formula Breakdown ({{ $payableSettlement['name'] ?? 'Company Payable' }})</span>
                        <span class="text-[11px] font-bold text-indigo-700 font-mono">Formula: {{ collect($items)->map(fn ($i) => ($i['role'] === 'subtract' ? '− ' : '+ ').$i['name'])->join(' ') }}</span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-1">
                        @foreach($items as $catItem)
                            <div class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50/70 px-3.5 py-2.5 text-xs">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="font-black {{ $catItem['role'] === 'subtract' ? 'text-rose-600' : 'text-emerald-600' }} text-sm leading-none">
                                        {{ $catItem['role'] === 'subtract' ? '−' : '+' }}
                                    </span>
                                    <span class="font-semibold text-slate-800 truncate" title="{{ $catItem['name'] }}">{{ $catItem['name'] }}</span>
                                </div>
                                <span class="font-mono font-bold shrink-0 ml-2 {{ $catItem['role'] === 'subtract' ? 'text-rose-700' : 'text-slate-900' }}">
                                    ₹{{ number_format((float) $catItem['amount'], 2) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Verified Payments Section -->
            @if(!empty($payments))
                <div class="rounded-2xl border border-emerald-100 bg-white p-5 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-black uppercase tracking-wider text-emerald-900">Verified Payments Received Under Payments Header</span>
                        <span class="text-xs font-bold font-mono text-emerald-800">Total Received: ₹{{ number_format($paymentsTotal, 2) }}</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-slate-100 text-[10px] font-extrabold uppercase text-slate-400">
                                    <th class="py-2 pr-3">Date</th>
                                    <th class="py-2 px-3">Method & Notes</th>
                                    <th class="py-2 px-3">Account / Ref</th>
                                    <th class="py-2 px-3">Status</th>
                                    <th class="py-2 pl-3 text-right">Amount Received</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50 font-medium">
                                @foreach($payments as $pmt)
                                    <tr class="hover:bg-slate-50/50">
                                        <td class="py-2.5 pr-3 text-slate-700 font-mono text-[11px]">{{ \Carbon\Carbon::parse($pmt['business_date'])->format('d M Y') }}</td>
                                        <td class="py-2.5 px-3 text-slate-900 font-bold">
                                            <span>{{ $pmt['notes'] }}</span>
                                        </td>
                                        <td class="py-2.5 px-3 text-slate-500 font-mono text-[11px]">
                                            {{ $pmt['company_account'] ?? 'Company Cash/Bank' }} {{ $pmt['reference'] ? '('.$pmt['reference'].')' : '' }}
                                        </td>
                                        <td class="py-2.5 px-3">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800">Verified</span>
                                        </td>
                                        <td class="py-2.5 pl-3 text-right font-mono font-black text-emerald-700">
                                            ₹{{ number_format((float) $pmt['amount'], 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    @else
        <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center space-y-2">
            <p class="text-sm font-bold text-slate-800">No active settlement configuration found for this shop.</p>
            <p class="text-xs text-slate-500">Configure a Company Payable formula to automatically compute the net balance.</p>
            <a href="{{ route('admin.cashbook.settings.shop.settlements.create', $currentShop->slug ?: $currentShop->shop_id) }}" class="inline-flex items-center gap-1.5 mt-3 px-4 py-2 rounded-xl bg-indigo-700 text-white text-xs font-bold hover:bg-indigo-800">
                + Create Settlement Formula
            </a>
        </div>
    @endif

    @if($otherCustomSettlements->isNotEmpty())
        <div class="space-y-2 pt-2">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Other Custom Settlements</span>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($otherCustomSettlements as $otherSettlement)
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                        <h3 class="text-xs font-bold text-slate-700">{{ $otherSettlement['name'] }}</h3>
                        <p class="mt-1 font-mono text-lg font-black {{ ($otherSettlement['netSettlement'] ?? 0) < 0 ? 'text-rose-700' : 'text-slate-900' }}">
                            ₹{{ number_format((float) ($otherSettlement['netSettlement'] ?? 0), 2) }}
                        </p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</section>

