@php
    $currentShopSlugOrId = $currentShop->slug ?: $currentShop->shop_id;
    $payableSettlement = collect($configuredSettlements)->firstWhere('is_company_payable', true)
        ?? collect($configuredSettlements)->firstWhere('relation_type', 'default_company_payable')
        ?? collect($configuredSettlements)->first();
    $verifiedPaymentsList = $payableSettlement['payments'] ?? [];
    $verifiedPaymentsTotal = (float) ($payableSettlement['verified_payments_total'] ?? 0);
@endphp

<section class="space-y-6" aria-label="Shop Cashbook Operations">
    <div class="flex items-center justify-between border-b border-slate-200/80 pb-3">
        <div>
            <h2 class="text-base font-black uppercase tracking-wider text-slate-950 flex items-center gap-2">
                <span class="inline-block w-3 h-3 rounded-full bg-slate-900"></span>
                Operations
            </h2>
            <p class="text-xs text-slate-500 font-medium">Execution cards and recent operational activity (latest 5 records each)</p>
        </div>
    </div>

    <!-- 1. ▼ VERIFIED PAYMENTS RECEIVED (OPEN BY DEFAULT) -->
    <div class="rounded-3xl border border-emerald-100 bg-white p-4 sm:p-6 shadow-xs space-y-4" x-data="{ expanded: true }">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 cursor-pointer select-none" @click="expanded = !expanded">
            <div class="flex items-center gap-3 flex-wrap">
                <button type="button" class="p-1 rounded-lg text-emerald-800 hover:text-emerald-950 transition cursor-pointer">
                    <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': expanded }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <h3 class="text-sm font-black uppercase tracking-wider text-emerald-950 flex items-center gap-2">
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-600"></span>
                    Verified Payments Received
                </h3>
                <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-bold text-emerald-800">
                    {{ count($verifiedPaymentsList) }} {{ Str::plural('record', count($verifiedPaymentsList)) }}
                </span>
            </div>

            <div class="flex items-center gap-4 flex-wrap">
                <span class="font-mono text-xs font-black text-emerald-900 tabular-nums">
                    Total: ₹{{ number_format($verifiedPaymentsTotal, 2) }}
                </span>
                <a href="{{ route('admin.cashbook.shop.history.payments', $currentShopSlugOrId) }}"
                   @click.stop
                   class="inline-flex items-center gap-1 text-xs font-extrabold text-emerald-800 hover:text-emerald-950 transition">
                    <span>View More</span>
                    <span>&rarr;</span>
                </a>
            </div>
        </div>

        <div x-show="expanded" class="pt-2">
            @if(!empty($verifiedPaymentsList))
                <div class="overflow-x-auto rounded-2xl border border-emerald-100">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-emerald-100 text-[11px] font-extrabold uppercase tracking-wider text-emerald-900 bg-emerald-50/50">
                                <th class="py-3 px-4 whitespace-nowrap">Date</th>
                                <th class="py-3 px-4 whitespace-nowrap">Payment Method / Destination</th>
                                <th class="py-3 px-4 whitespace-nowrap">Notes / Reference</th>
                                <th class="py-3 px-4 text-right whitespace-nowrap">Amount</th>
                                <th class="py-3 px-4 text-center whitespace-nowrap">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-emerald-50 font-mono">
                            @foreach(array_slice($verifiedPaymentsList, 0, 5) as $pay)
                                <tr class="hover:bg-emerald-50/40 transition-colors">
                                    <td class="py-3 px-4 font-sans font-bold text-slate-900 whitespace-nowrap">
                                        {{ $pay['business_date'] ? \Illuminate\Support\Carbon::parse($pay['business_date'])->format('d M Y') : '—' }}
                                    </td>
                                    <td class="py-3 px-4 font-sans">
                                        <span class="font-extrabold text-slate-800 block">{{ $pay['payment_method'] ?? 'Direct Payment' }}</span>
                                        <span class="text-[10px] text-slate-500 font-mono">{{ $pay['company_account'] ?? 'Company Account' }}</span>
                                    </td>
                                    <td class="py-3 px-4 font-sans text-slate-600 text-xs">
                                        {{ $pay['notes'] ?? 'Payment Received' }}
                                        @if(!empty($pay['reference']))
                                            <span class="text-[10px] text-slate-400 font-mono block">{{ $pay['reference'] }}</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-right font-black text-emerald-800 tabular-nums whitespace-nowrap">
                                        ₹{{ number_format((float) ($pay['amount'] ?? 0), 2) }}
                                    </td>
                                    <td class="py-3 px-4 text-center whitespace-nowrap">
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-extrabold text-emerald-800 uppercase tracking-wide">
                                            Verified
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="rounded-2xl border border-dashed border-emerald-200 p-6 text-center text-xs text-emerald-700/80">
                    No verified payments recorded for this period.
                </div>
            @endif
        </div>
    </div>

    <!-- 2. ▶ PETTY TRANSACTIONS (COLLAPSED BY DEFAULT) -->
    <div class="rounded-3xl border border-slate-200/80 bg-white p-4 sm:p-6 shadow-xs space-y-4" x-data="{ expanded: false }">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 cursor-pointer select-none" @click="expanded = !expanded">
            <div class="flex items-center gap-3 flex-wrap">
                <button type="button" class="p-1 rounded-lg text-slate-500 hover:text-slate-900 transition cursor-pointer">
                    <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': expanded }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <h3 class="text-sm font-black uppercase tracking-wider text-slate-900 flex items-center gap-2">
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-purple-600"></span>
                    Petty Transactions
                </h3>
                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-bold text-slate-700">
                    {{ $pettyHistory->count() }} {{ Str::plural('entry', $pettyHistory->count()) }}
                </span>
            </div>

            <div class="flex items-center gap-4 flex-wrap">
                <a href="{{ route('admin.cashbook.shop.history.petty', $currentShopSlugOrId) }}"
                   @click.stop
                   class="inline-flex items-center gap-1 text-xs font-extrabold text-purple-700 hover:text-purple-900 transition">
                    <span>View More</span>
                    <span>&rarr;</span>
                </a>
            </div>
        </div>

        <div x-show="expanded" class="pt-2" style="display: none;">
            @include('admin.cashbook.shops.partials.petty-table', [
                'pettyTransactions' => $pettyHistory->take(5),
                'currentShop' => $currentShop,
                'isFull' => false,
            ])
        </div>
    </div>

    <!-- 3. ▶ PAYMENTS & ALLOCATION (COLLAPSED BY DEFAULT) -->
    <div class="rounded-3xl border border-slate-200/80 bg-white p-4 sm:p-6 shadow-xs space-y-4" x-data="{ expanded: false }">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 cursor-pointer select-none" @click="expanded = !expanded">
            <div class="flex items-center gap-3 flex-wrap">
                <button type="button" class="p-1 rounded-lg text-slate-500 hover:text-slate-900 transition cursor-pointer">
                    <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': expanded }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <h3 class="text-sm font-black uppercase tracking-wider text-slate-900 flex items-center gap-2">
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-blue-600"></span>
                    Payments &amp; Allocation
                </h3>
                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-bold text-slate-700">
                    {{ $recentPayments->count() }} recent
                </span>
            </div>

            <div class="flex items-center gap-2 sm:gap-4 flex-wrap">
                <button type="button"
                        @click.stop="openReceivePaymentModal()"
                        class="rounded-xl bg-slate-900 px-3 py-1.5 text-xs font-black uppercase tracking-wider text-white hover:bg-slate-800 transition cursor-pointer">
                    + Receive Payment
                </button>
                <a href="{{ route('admin.cashbook.shop.history.payments', $currentShopSlugOrId) }}"
                   @click.stop
                   class="inline-flex items-center gap-1 text-xs font-extrabold text-blue-700 hover:text-blue-900 transition">
                    <span>View More</span>
                    <span>&rarr;</span>
                </a>
            </div>
        </div>

        <div x-show="expanded" class="pt-2" style="display: none;">
            @include('admin.cashbook.shops.partials.payments-table', [
                'payments' => $recentPayments->take(5),
                'currentShop' => $currentShop,
                'isFull' => false,
            ])
        </div>
    </div>

    <!-- 4. ▶ CHEQUES (COLLAPSED BY DEFAULT) -->
    <div class="rounded-3xl border border-slate-200/80 bg-white p-4 sm:p-6 shadow-xs space-y-4" x-data="{ expanded: false }">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 cursor-pointer select-none" @click="expanded = !expanded">
            <div class="flex items-center gap-3 flex-wrap">
                <button type="button" class="p-1 rounded-lg text-slate-500 hover:text-slate-900 transition cursor-pointer">
                    <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': expanded }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <h3 class="text-sm font-black uppercase tracking-wider text-slate-900 flex items-center gap-2">
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-violet-600"></span>
                    Cheques
                </h3>
                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-bold text-slate-700">
                    {{ $recentCheques->count() }} recent
                </span>
            </div>

            <div class="flex items-center gap-4 flex-wrap">
                <a href="{{ route('admin.cashbook.shop.history.cheques', $currentShopSlugOrId) }}"
                   @click.stop
                   class="inline-flex items-center gap-1 text-xs font-extrabold text-violet-700 hover:text-violet-900 transition">
                    <span>View More</span>
                    <span>&rarr;</span>
                </a>
            </div>
        </div>

        <div x-show="expanded" class="pt-2" style="display: none;">
            @include('admin.cashbook.shops.partials.cheques-table', [
                'cheques' => $recentCheques->take(5),
                'currentShop' => $currentShop,
                'isFull' => false,
            ])
        </div>
    </div>

    <!-- 5. ▶ BANKING VERIFICATION (COLLAPSED BY DEFAULT) -->
    <div class="rounded-3xl border border-slate-200/80 bg-white p-4 sm:p-6 shadow-xs space-y-4" x-data="{ expanded: false }">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 cursor-pointer select-none" @click="expanded = !expanded">
            <div class="flex items-center gap-3 flex-wrap">
                <button type="button" class="p-1 rounded-lg text-slate-500 hover:text-slate-900 transition cursor-pointer">
                    <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': expanded }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <h3 class="text-sm font-black uppercase tracking-wider text-slate-900 flex items-center gap-2">
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-600"></span>
                    Banking Verification
                </h3>
                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-bold text-slate-700">
                    {{ $connectedBankAccounts->count() }} connected {{ Str::plural('account', $connectedBankAccounts->count()) }}
                </span>
            </div>

            <div class="flex items-center gap-4 flex-wrap">
                <a href="{{ route('admin.cashbook.shop.history.banking', $currentShopSlugOrId) }}"
                   @click.stop
                   class="inline-flex items-center gap-1 text-xs font-extrabold text-emerald-700 hover:text-emerald-900 transition">
                    <span>View More</span>
                    <span>&rarr;</span>
                </a>
            </div>
        </div>

        <div x-show="expanded" class="pt-2" style="display: none;">
            @include('admin.cashbook.shops.partials.banking-table', [
                'connectedBankAccounts' => $connectedBankAccounts,
                'selectedBankId' => $selectedBankId,
                'selectedBankAccount' => $selectedBankAccount,
                'bankingStatusFilter' => $bankingStatusFilter,
                'bankingTotals' => $bankingTotals,
                'bankingPagination' => $bankingPagination,
                'currentShop' => $currentShop,
                'isFull' => false,
            ])
        </div>
    </div>

    <!-- 6. ▶ ADJUSTMENTS (COLLAPSED BY DEFAULT) -->
    <div class="rounded-3xl border border-slate-200/80 bg-white p-4 sm:p-6 shadow-xs space-y-4" x-data="{ expanded: false }">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 cursor-pointer select-none" @click="expanded = !expanded">
            <div class="flex items-center gap-3 flex-wrap">
                <button type="button" class="p-1 rounded-lg text-slate-500 hover:text-slate-900 transition cursor-pointer">
                    <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': expanded }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <h3 class="text-sm font-black uppercase tracking-wider text-slate-900 flex items-center gap-2">
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-amber-600"></span>
                    Adjustments
                </h3>
                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-bold text-slate-700">
                    {{ $recentAdjustments->count() }} recent
                </span>
            </div>

            <div class="flex items-center gap-2 sm:gap-4 flex-wrap">
                <button type="button"
                        @click.stop="showAddAdjustmentModal = true"
                        class="rounded-xl bg-amber-600 px-3 py-1.5 text-xs font-black uppercase tracking-wider text-white hover:bg-amber-700 transition cursor-pointer">
                    + Make Adjustment
                </button>
                <a href="{{ route('admin.cashbook.shop.history.adjustments', $currentShopSlugOrId) }}"
                   @click.stop
                   class="inline-flex items-center gap-1 text-xs font-extrabold text-amber-700 hover:text-amber-900 transition">
                    <span>View More</span>
                    <span>&rarr;</span>
                </a>
            </div>
        </div>

        <div x-show="expanded" class="pt-2" style="display: none;">
            @include('admin.cashbook.shops.partials.adjustments-table', [
                'adjustments' => $recentAdjustments->take(5),
                'currentShop' => $currentShop,
                'isFull' => false,
            ])
        </div>
    </div>
</section>
